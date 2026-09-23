<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\ItemCategoryRule;
use App\Support\ProductNameNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function getCategoriesWithSpending(int $userId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $start = $startDate ?: Carbon::now()->startOfMonth()->format('Y-m-d');
        $end = $endDate ?: Carbon::now()->format('Y-m-d');

        $categories = Category::forUser($userId)
            ->withCount(['items' => fn ($q) => $q->whereHas('invoice', fn ($q2) => $q2->where('user_id', $userId)
                ->whereDateBetween('issued_at', $start, $end))])
            ->get();

        $spendingByCategory = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->whereDateBetween('invoices.issued_at', $start, $end)
            ->whereNotNull('invoices_items.category_id')
            ->select('invoices_items.category_id', DB::raw('SUM(invoices_items.total_price) as total'))
            ->groupBy('invoices_items.category_id')
            ->pluck('total', 'category_id');

        return $categories->map(function (Category $cat) use ($spendingByCategory) {
            $cat->total_spent = (float) ($spendingByCategory[$cat->id] ?? 0);

            return $cat;
        })->sortByDesc('total_spent')->values();
    }

    public function countUncategorizedItems(int $userId, ?string $startDate = null, ?string $endDate = null): int
    {
        $start = $startDate ?: Carbon::now()->startOfMonth()->format('Y-m-d');
        $end = $endDate ?: Carbon::now()->format('Y-m-d');

        return InvoiceItem::whereNull('category_id')
            ->whereHas('invoice', fn ($q) => $q->where('user_id', $userId)
                ->whereDateBetween('issued_at', $start, $end))
            ->count();
    }

    public function assignItem(InvoiceItem $item, ?int $categoryId): void
    {
        $item->update([
            'category_id' => $categoryId,
            'categorization_source' => $categoryId === null ? null : InvoiceItem::SOURCE_MANUAL,
        ]);

        $key = ItemCategoryRule::keyFor($item->description);

        if ($key === '') {
            return;
        }

        $identity = ['user_id' => $item->invoice->user_id, 'description_key' => $key];

        if ($categoryId === null) {
            ItemCategoryRule::where($identity)->delete();

            return;
        }

        ItemCategoryRule::updateOrCreate($identity, [
            'category_id' => $categoryId,
            'source' => ItemCategoryRule::SOURCE_MANUAL,
        ]);
    }

    /**
     * Regras aprendidas (correções manuais e acertos da IA) têm prioridade
     * sobre keywords. Itens que sobram são resolvidos pela IA em AiCategorizeItemsJob.
     */
    public function autoCategorize(int $userId): int
    {
        $rules = ItemCategoryRule::where('user_id', $userId)->get()->keyBy('description_key');
        $keywords = $this->buildKeywordIndex($userId);

        if ($rules->isEmpty() && $keywords === []) {
            return 0;
        }

        $totalCategorized = 0;
        $updates = [];

        InvoiceItem::whereNull('category_id')
            ->whereHas('invoice', fn ($q) => $q->where('user_id', $userId))
            ->select('id', 'description')
            ->chunkById(500, function ($items) use ($rules, $keywords, &$updates, &$totalCategorized) {
                foreach ($items as $item) {
                    $key = ItemCategoryRule::keyFor($item->description);
                    $rule = $rules->get($key);

                    if ($rule !== null) {
                        $source = $rule->source === ItemCategoryRule::SOURCE_AI
                            ? InvoiceItem::SOURCE_AI
                            : InvoiceItem::SOURCE_LEARNED;
                        $updates[$rule->category_id][$source][] = $item->id;
                    } elseif (($categoryId = $this->matchKeyword($key, $keywords)) !== null) {
                        $updates[$categoryId][InvoiceItem::SOURCE_KEYWORD][] = $item->id;
                    } else {
                        continue;
                    }

                    $totalCategorized++;
                }
            });

        foreach ($updates as $categoryId => $idsBySource) {
            foreach ($idsBySource as $source => $itemIds) {
                foreach (array_chunk($itemIds, 500) as $chunk) {
                    InvoiceItem::whereIn('id', $chunk)
                        ->update(['category_id' => $categoryId, 'categorization_source' => $source]);
                }
            }
        }

        return $totalCategorized;
    }

    /**
     * Keywords normalizadas (sem acento/pontuação), da mais específica (maior)
     * para a mais genérica; keywords da categoria "Outros" ficam por último.
     *
     * @return list<array{0: int, 1: string, 2: bool}> [category_id, keyword, é "Outros"]
     */
    private function buildKeywordIndex(int $userId): array
    {
        $index = [];

        foreach (Category::forUser($userId)->whereNotNull('keywords')->get() as $category) {
            foreach ($category->keywords ?? [] as $keyword) {
                $normalized = ProductNameNormalizer::normalize((string) $keyword);

                if ($normalized !== '') {
                    $index[] = [$category->id, $normalized, $category->name === 'Outros'];
                }
            }
        }

        usort($index, fn ($a, $b) => [$a[2], strlen($b[1])] <=> [$b[2], strlen($a[1])]);

        return $index;
    }

    /**
     * A keyword casa com o início de uma palavra da descrição: "REFRIG"
     * pega "REFRIGERANTE", mas "OVO" não pega "NOVO".
     *
     * @param  list<array{0: int, 1: string, 2: bool}>  $keywords
     */
    private function matchKeyword(string $descriptionKey, array $keywords): ?int
    {
        $haystack = ' '.$descriptionKey;

        foreach ($keywords as [$categoryId, $keyword]) {
            if (str_contains($haystack, ' '.$keyword)) {
                return $categoryId;
            }
        }

        return null;
    }
}
