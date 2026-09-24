<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\ItemCategoryRule;
use App\Support\Period;
use App\Support\ProductNameNormalizer;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    private const UNCATEGORIZED_PER_PAGE = 20;

    private const DETAIL_TOP = 5;

    /** Origens automáticas (o que "reverter" desfaz); correções manuais nunca são tocadas. */
    private const AUTO_SOURCES = [InvoiceItem::SOURCE_KEYWORD, InvoiceItem::SOURCE_LEARNED, InvoiceItem::SOURCE_AI];

    public function __construct(private readonly ProductAliasService $aliasService) {}

    /**
     * Cada categoria com itens/gasto no período, variação contra o período anterior de mesma
     * duração (`delta_pct`, null sem base ou em "Tudo") e o total de itens de todos os tempos
     * (`total_items_count`, usado para avisar o que a exclusão/mesclagem afeta).
     */
    public function getCategoriesWithSpending(int $userId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        [$start, $end] = $this->resolvePeriod($startDate, $endDate);

        $current = $this->spendingByCategory($userId, $start, $end);
        $previous = Period::isAllTime($start) ? collect() : $this->spendingByCategory($userId, ...Period::previous($start, $end));
        $lifetime = $this->itemsOf($userId)->whereNotNull('invoices_items.category_id')
            ->selectRaw('invoices_items.category_id, COUNT(*) as items')
            ->groupBy('invoices_items.category_id')
            ->pluck('items', 'category_id');

        return Category::forUser($userId)->get()->map(function (Category $cat) use ($current, $previous, $lifetime) {
            $cat->total_spent = (float) ($current[$cat->id]->total ?? 0);
            $cat->items_count = (int) ($current[$cat->id]->items ?? 0);
            $cat->total_items_count = (int) ($lifetime[$cat->id] ?? 0);
            $cat->delta_pct = isset($previous[$cat->id]) ? Period::deltaPct($cat->total_spent, (float) $previous[$cat->id]->total) : null;

            return $cat;
        })->sort(fn (Category $a, Category $b) => [$b->total_spent, $a->name] <=> [$a->total_spent, $b->name])->values();
    }

    public function countUncategorizedItems(int $userId, ?string $startDate = null, ?string $endDate = null): int
    {
        return $this->uncategorizedSummary($userId, $startDate, $endDate)['count'];
    }

    /**
     * @return array{count: int, total: float}
     */
    public function uncategorizedSummary(int $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->resolvePeriod($startDate, $endDate);

        $row = $this->itemsOf($userId)
            ->whereNull('invoices_items.category_id')
            ->whereDateBetween('invoices.issued_at', $start, $end)
            ->selectRaw('COUNT(*) as items, COALESCE(SUM(invoices_items.total_price), 0) as total')
            ->first();

        return ['count' => (int) $row->items, 'total' => (float) $row->total];
    }

    public function paginateUncategorized(int $userId, ?string $startDate = null, ?string $endDate = null): LengthAwarePaginator
    {
        [$start, $end] = $this->resolvePeriod($startDate, $endDate);

        $page = $this->itemsOf($userId)
            ->leftJoin('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'invoices.issuer_id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            })
            ->whereNull('invoices_items.category_id')
            ->whereDateBetween('invoices.issued_at', $start, $end)
            ->select('invoices_items.*', 'invoices.issued_at as invoice_issued_at')
            ->selectRaw('COALESCE(issuer_nicknames.nickname, issuers.name) as issuer_name')
            ->orderByDesc('invoices.issued_at')
            ->orderBy('invoices_items.id')
            ->paginate(self::UNCATEGORIZED_PER_PAGE)
            ->withQueryString();

        $this->aliasService->attachCanonicalNames($page->getCollection(), $userId);

        return $page;
    }

    /**
     * Visão de uma categoria: gasto no período (+ variação), série mensal de 12 meses, produtos e emissores
     * onde mais se gasta nela.
     *
     * @return array{total: float, items: int, delta_pct: ?float, monthly: list<array{month: string, total: float}>, top_products: list<array<string, mixed>>, top_issuers: list<array<string, mixed>>}
     */
    public function detail(int $userId, Category $category, ?string $startDate = null, ?string $endDate = null): array
    {
        [$start, $end] = $this->resolvePeriod($startDate, $endDate);

        $current = $this->categoryTotals($userId, $category, $start, $end);
        $previous = Period::isAllTime($start) ? null : $this->categoryTotals($userId, $category, ...Period::previous($start, $end));

        return [
            'total' => $current['total'],
            'items' => $current['items'],
            'delta_pct' => $previous === null ? null : Period::deltaPct($current['total'], $previous['total']),
            'monthly' => $this->monthlySeries($userId, $category),
            'top_products' => $this->topProducts($userId, $category, $start, $end),
            'top_issuers' => $this->topIssuers($userId, $category, $start, $end),
        ];
    }

    /**
     * Quantos itens sem categoria (todas as notas) as palavras-chave informadas pegariam, com alguns exemplos —
     * mesma regra de casamento da auto-categorização.
     *
     * @param  list<string>  $keywords
     * @return array{count: int, samples: list<string>}
     */
    public function previewKeywords(int $userId, array $keywords): array
    {
        $index = [];
        foreach ($keywords as $keyword) {
            $normalized = ProductNameNormalizer::normalize($keyword);
            if ($normalized !== '') {
                $index[] = [0, $normalized, false];
            }
        }

        if ($index === []) {
            return ['count' => 0, 'samples' => []];
        }

        $count = 0;
        $samples = [];

        $this->itemsOf($userId)
            ->whereNull('invoices_items.category_id')
            ->select('invoices_items.id', 'invoices_items.description')
            ->chunkById(1000, function ($items) use ($index, &$count, &$samples) {
                foreach ($items as $item) {
                    if ($this->matchKeyword(ItemCategoryRule::keyFor($item->description), $index) === null) {
                        continue;
                    }

                    $count++;
                    if (count($samples) < 3 && ! in_array($item->description, $samples, true)) {
                        $samples[] = $item->description;
                    }
                }
            }, 'invoices_items.id', 'id');

        return ['count' => $count, 'samples' => $samples];
    }

    public function countAutoCategorized(int $userId): int
    {
        return $this->itemsOf($userId)->whereIn('invoices_items.categorization_source', self::AUTO_SOURCES)->count();
    }

    /** Desfaz o que a auto-categorização fez (palavras-chave, regras aprendidas e IA); manuais ficam. */
    public function revertAutoCategorization(int $userId): int
    {
        return InvoiceItem::whereIn('categorization_source', self::AUTO_SOURCES)
            ->whereIn('invoice_id', $this->userInvoiceIds($userId))
            ->update(['category_id' => null, 'categorization_source' => null]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePeriod(?string $startDate, ?string $endDate): array
    {
        return [
            $startDate ?: Carbon::now()->startOfMonth()->format('Y-m-d'),
            $endDate ?: Carbon::now()->format('Y-m-d'),
        ];
    }

    /** Itens das notas do usuário (join em invoices; itens só existem em nota autorizada). */
    private function itemsOf(int $userId): Builder
    {
        return InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId);
    }

    private function userInvoiceIds(int $userId)
    {
        return DB::table('invoices')->where('user_id', $userId)->select('id');
    }

    /**
     * @return Collection<int, object{items: int, total: string}> por category_id
     */
    private function spendingByCategory(int $userId, string $start, string $end): Collection
    {
        return $this->itemsOf($userId)
            ->whereDateBetween('invoices.issued_at', $start, $end)
            ->whereNotNull('invoices_items.category_id')
            ->selectRaw('invoices_items.category_id, COUNT(*) as items, SUM(invoices_items.total_price) as total')
            ->groupBy('invoices_items.category_id')
            ->get()
            ->keyBy('category_id');
    }

    /**
     * @return array{total: float, items: int}
     */
    private function categoryTotals(int $userId, Category $category, string $start, string $end): array
    {
        $row = $this->itemsOf($userId)
            ->where('invoices_items.category_id', $category->id)
            ->whereDateBetween('invoices.issued_at', $start, $end)
            ->selectRaw('COUNT(*) as items, COALESCE(SUM(invoices_items.total_price), 0) as total')
            ->first();

        return ['total' => (float) $row->total, 'items' => (int) $row->items];
    }

    private function monthlySeries(int $userId, Category $category): array
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth();

        $totals = $this->itemsOf($userId)
            ->where('invoices_items.category_id', $category->id)
            ->where('invoices.issued_at', '>=', $start)
            ->selectRaw('substr(invoices.issued_at, 1, 7) as month, SUM(invoices_items.total_price) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        return collect(range(0, 11))->map(function (int $offset) use ($start, $totals) {
            $month = $start->copy()->addMonths($offset)->format('Y-m');

            return ['month' => $month, 'total' => (float) ($totals[$month] ?? 0)];
        })->all();
    }

    private function topProducts(int $userId, Category $category, string $start, string $end): array
    {
        $name = $this->aliasService->canonicalNameSql();

        return $this->aliasService->joinCanonicalNames(
            $this->itemsOf($userId)
                ->where('invoices_items.category_id', $category->id)
                ->whereDateBetween('invoices.issued_at', $start, $end),
            $userId
        )
            ->selectRaw("{$name} as name, COUNT(*) as purchases, SUM(invoices_items.total_price) as total")
            ->groupBy(DB::raw($name))
            ->orderByDesc('total')
            ->limit(self::DETAIL_TOP)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'purchases' => (int) $row->purchases, 'total' => (float) $row->total])
            ->all();
    }

    private function topIssuers(int $userId, Category $category, string $start, string $end): array
    {
        return $this->itemsOf($userId)
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            })
            ->where('invoices_items.category_id', $category->id)
            ->whereDateBetween('invoices.issued_at', $start, $end)
            ->selectRaw('issuers.id as issuer_id, COALESCE(issuer_nicknames.nickname, issuers.name) as issuer_name, SUM(invoices_items.total_price) as total')
            ->groupBy('issuers.id', 'issuer_nicknames.nickname', 'issuers.name')
            ->orderByDesc('total')
            ->limit(self::DETAIL_TOP)
            ->get()
            ->map(fn ($row) => ['issuer_id' => (int) $row->issuer_id, 'name' => $row->issuer_name, 'total' => (float) $row->total])
            ->all();
    }

    /** Item de nota do usuário; de outro usuário, ModelNotFoundException (404) — 403 confirmaria que o id existe. */
    public function findItemForUser(int $userId, int $itemId): InvoiceItem
    {
        return $this->itemsOf($userId)->select('invoices_items.*')->findOrFail($itemId);
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
