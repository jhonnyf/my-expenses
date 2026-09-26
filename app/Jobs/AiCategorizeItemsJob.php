<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\ItemCategoryRule;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ItemCategoryAiClassifierService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Última camada da auto-categorização: itens que nem regras aprendidas nem
 * keywords resolveram vão para o Gemini (só plano Pro, como as demais
 * sugestões de IA). Cada acerto vira regra, então o mesmo produto não volta.
 */
class AiCategorizeItemsJob implements ShouldQueue
{
    use Queueable;

    private const MAX_DESCRIPTIONS_PER_RUN = 200;

    private const BATCH_SIZE = 50;

    private const MISS_TTL_DAYS = 7;

    public function __construct(private readonly int $userId) {}

    public function handle(ItemCategoryAiClassifierService $classifier): void
    {
        if (! $classifier->isConfigured() || ! User::find($this->userId)?->isPro()) {
            return;
        }

        $categories = Category::forUser($this->userId)->pluck('name', 'id')->all();
        $itemIdsByKey = $this->uncategorizedItemIdsByKey();

        if ($categories === [] || $itemIdsByKey === []) {
            return;
        }

        foreach (array_chunk($itemIdsByKey, self::BATCH_SIZE, true) as $batch) {
            if (! $this->classifyBatch($classifier, $batch, $categories)) {
                return;
            }
        }
    }

    /**
     * @return array<string, list<int>> description_key => ids dos itens
     */
    private function uncategorizedItemIdsByKey(): array
    {
        $idsByKey = [];

        InvoiceItem::whereNull('category_id')
            ->whereHas('invoice', fn ($q) => $q->where('user_id', $this->userId))
            ->select('id', 'description')
            ->chunkById(500, function ($items) use (&$idsByKey) {
                foreach ($items as $item) {
                    $key = ItemCategoryRule::keyFor($item->description);

                    if ($key === '' || Cache::has($this->missCacheKey($key))) {
                        continue;
                    }

                    if (! isset($idsByKey[$key]) && count($idsByKey) >= self::MAX_DESCRIPTIONS_PER_RUN) {
                        continue;
                    }

                    $idsByKey[$key][] = $item->id;
                }
            });

        return $idsByKey;
    }

    /**
     * @param  array<string, list<int>>  $batch
     * @param  array<int, string>  $categories
     * @return bool false quando o Gemini falhou (interrompe o job, sem gastar mais cota)
     */
    private function classifyBatch(ItemCategoryAiClassifierService $classifier, array $batch, array $categories): bool
    {
        $keys = array_keys($batch);
        $classified = $classifier->classify($keys, $categories);

        if ($classified === null) {
            return false;
        }

        foreach ($keys as $index => $key) {
            if (isset($classified[$index])) {
                $this->applyClassification($key, $classified[$index], $batch[$key]);
            } else {
                Cache::put($this->missCacheKey($key), true, now()->addDays(self::MISS_TTL_DAYS));
            }
        }

        return true;
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function applyClassification(string $key, int $categoryId, array $itemIds): void
    {
        // firstOrCreate: nunca sobrescreve uma regra manual do usuário.
        $rule = ItemCategoryRule::firstOrCreate(
            ['user_id' => $this->userId, 'description_key' => $key],
            ['category_id' => $categoryId, 'source' => ItemCategoryRule::SOURCE_AI],
        );

        $source = $rule->source === ItemCategoryRule::SOURCE_AI
            ? InvoiceItem::SOURCE_AI
            : InvoiceItem::SOURCE_LEARNED;

        foreach (array_chunk($itemIds, 500) as $chunk) {
            InvoiceItem::whereIn('id', $chunk)->whereNull('category_id')
                ->update(['category_id' => $rule->category_id, 'categorization_source' => $source]);
        }

        DashboardService::flushCache($this->userId);
    }

    private function missCacheKey(string $key): string
    {
        return sprintf('ai_item_category_miss:%d:%s', $this->userId, sha1($key));
    }
}
