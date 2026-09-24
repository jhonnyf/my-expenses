<?php

namespace App\Services;

use App\Http\Requests\ReportFiltersRequest;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Support\FullTextQuery;
use App\Support\Period;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de gastos. Sem filtro de categoria nem de produto, o total é o das notas (líquido de desconto,
 * o mesmo das telas de compras, orçamentos e dashboard); com eles só dá para somar os itens que casam.
 */
class ReportService
{
    public const PER_PAGE = 50;

    /** DomPDF não aguenta relatórios muito longos: o PDF leva os primeiros itens, o CSV leva todos. */
    public const PDF_MAX_ITEMS = 2000;

    private const CSV_CHUNK = 1000;

    private const TOP_ISSUERS = 8;

    private const NO_ISSUER = 'Emissor não identificado';

    public function __construct(private readonly ProductAliasService $aliasService) {}

    // ─────────────────────────── Montagem do relatório ───────────────────────────

    /**
     * Relatório com todos os itens (exportações e e-mail). `$itemsLimit` corta a lista de itens (PDF);
     * `items_truncated` avisa quando houve corte.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildReportData(int $userId, array $filters, ?int $itemsLimit = null): array
    {
        $f = $this->normalize($filters);
        $report = $this->report($userId, $f);

        $itemsQuery = $this->itemsQuery($userId, $f);
        if ($itemsLimit !== null) {
            $itemsQuery->limit($itemsLimit);
        }
        $items = $itemsQuery->get();

        return [
            ...$report,
            'items' => $items,
            'items_truncated' => $itemsLimit !== null && $report['summary']->total_items > $items->count(),
        ];
    }

    /**
     * Relatório com os itens paginados (tela e API).
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildReportPage(int $userId, array $filters, ?int $perPage = null): array
    {
        $f = $this->normalize($filters);

        return [
            ...$this->report($userId, $f),
            'items' => $this->itemsQuery($userId, $f)->paginate($perPage ?? self::PER_PAGE),
        ];
    }

    /**
     * @param  array<string, mixed>  $f  filtros normalizados
     */
    private function report(int $userId, array $f): array
    {
        return [
            'summary' => $this->summary($userId, $f),
            'categoryBreakdown' => $this->categoryBreakdown($userId, $f),
            'monthly' => $this->monthly($userId, $f),
            'byIssuer' => $this->byIssuer($userId, $f),
            // Chaves snake_case (não startDate/endDate) de propósito: as views (report/index.blade.php,
            // report/pdf.blade.php) leem $filters['start_date'] etc. Chave errada aqui já causou 500 real
            // no export em PDF e fazia o form "esquecer" o período/emissor/categoria após gerar.
            'filters' => [
                'start_date' => $f['start_date'],
                'end_date' => $f['end_date'],
                'issuer_id' => $f['issuer_id'],
                'category_id' => $f['category_id'],
                'q' => $f['q'],
                'sort' => $f['sort'],
            ],
            'issuers' => Issuer::whereHas('invoices', fn ($q) => $q->where('user_id', $userId))
                ->with('nicknameForUser')
                ->orderBy('name')
                ->get()
                ->each->append('display_name'),
            'categories' => Category::forUser($userId)->orderBy('name')->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{start_date: string, end_date: string, issuer_id: mixed, category_id: mixed, q: string, sort: string}
     */
    private function normalize(array $filters): array
    {
        return [
            // ?: (não só ??) trata ausência e string vazia (campo de data limpo e reenviado) como "usar o padrão".
            'start_date' => ($filters['start_date'] ?? '') ?: Carbon::now()->startOfMonth()->format('Y-m-d'),
            'end_date' => ($filters['end_date'] ?? '') ?: Carbon::now()->format('Y-m-d'),
            'issuer_id' => ($filters['issuer_id'] ?? null) ?: null,
            'category_id' => ($filters['category_id'] ?? null) ?: null,
            'q' => trim((string) ($filters['q'] ?? '')),
            'sort' => in_array($filters['sort'] ?? null, ReportFiltersRequest::SORTS, true) ? $filters['sort'] : 'recent',
        ];
    }

    /**
     * Período efetivo do relatório (com os padrões aplicados).
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string}
     */
    public function resolvePeriod(array $filters): array
    {
        $f = $this->normalize($filters);

        return [$f['start_date'], $f['end_date']];
    }

    /** Com categoria ou produto filtrados, o total só pode ser a soma dos itens que casam. */
    private function isPartial(array $f): bool
    {
        return $f['category_id'] !== null || $f['q'] !== '';
    }

    // ─────────────────────────── Consultas ───────────────────────────

    /**
     * Itens do usuário com o nome canônico do produto, filtrados. `$period` sobrepõe o do filtro
     * (séries mensais e comparação com o período anterior).
     *
     * @param  array{0: string, 1: string}|null  $period
     */
    private function itemsBase(int $userId, array $f, ?array $period = null): Builder
    {
        $query = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            // LEFT: nota sem emissor identificado continua no relatório.
            ->leftJoin('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('categories', 'categories.id', '=', 'invoices_items.category_id')
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            })
            ->where('invoices.user_id', $userId)
            ->whereDateBetween('invoices.issued_at', ...($period ?? [$f['start_date'], $f['end_date']]))
            ->when($f['issuer_id'], fn ($q) => $q->where('invoices.issuer_id', $f['issuer_id']));

        $this->aliasService->joinCanonicalNames($query, $userId);

        if ($f['category_id'] === ReportFiltersRequest::NO_CATEGORY) {
            $query->whereNull('invoices_items.category_id');
        } elseif ($f['category_id'] !== null) {
            $query->where('invoices_items.category_id', $f['category_id']);
        }

        if ($f['q'] !== '') {
            FullTextQuery::applyOr($query, $f['q'], [], ['invoices_items.description', 'product_aliases.canonical_name']);
        }

        return $query;
    }

    /** Notas do usuário no período (base do total líquido, sem filtro de categoria/produto). */
    private function invoicesBase(int $userId, array $f, ?array $period = null): Builder
    {
        return Invoice::where('invoices.user_id', $userId)
            ->whereDateBetween('invoices.issued_at', ...($period ?? [$f['start_date'], $f['end_date']]))
            ->when($f['issuer_id'], fn ($q) => $q->where('invoices.issuer_id', $f['issuer_id']));
    }

    private function itemsQuery(int $userId, array $f): Builder
    {
        $nameSql = $this->aliasService->canonicalNameSql();

        $query = $this->itemsBase($userId, $f)->select(
            'invoices_items.id as item_id',
            'invoices_items.category_id',
            'invoices_items.description as raw_description',
            'product_aliases.canonical_name',
            DB::raw("{$nameSql} as description"),
            'invoices_items.quantity',
            'invoices_items.unit',
            'invoices_items.unit_price',
            'invoices_items.total_price',
            'invoices.issued_at',
            DB::raw("COALESCE(issuer_nicknames.nickname, issuers.name, '".self::NO_ISSUER."') as issuer_name"),
            DB::raw("COALESCE(categories.name, 'Sem categoria') as category_name"),
            DB::raw("COALESCE(categories.color, '#94A3B8') as category_color")
        );

        match ($f['sort']) {
            'oldest' => $query->orderBy('invoices.issued_at'),
            'highest' => $query->orderByDesc('invoices_items.total_price'),
            'lowest' => $query->orderBy('invoices_items.total_price'),
            'name' => $query->orderByRaw("{$nameSql} asc"),
            default => $query->orderByDesc('invoices.issued_at'),
        };

        return $query->orderByDesc('invoices_items.id');
    }

    private function summary(int $userId, array $f): object
    {
        $partial = $this->isPartial($f);
        $items = $this->itemsBase($userId, $f)
            ->selectRaw('COALESCE(SUM(invoices_items.total_price), 0) as total, COUNT(invoices_items.id) as items, COUNT(DISTINCT invoices.id) as invoices')
            ->first();

        $invoices = $partial ? null : $this->invoicesBase($userId, $f)
            ->selectRaw('COALESCE(SUM(invoices.total_amount), 0) as total, COUNT(*) as invoices')
            ->first();

        $total = (float) ($partial ? $items->total : $invoices->total);
        $invoiceCount = (int) ($partial ? $items->invoices : $invoices->invoices);

        return (object) [
            'total_amount' => $total,
            'total_items' => (int) $items->items,
            'total_invoices' => $invoiceCount,
            'average_ticket' => $invoiceCount > 0 ? $total / $invoiceCount : 0.0,
            // "Ticket médio" é o gasto médio da compra inteira; filtrando, é o da parte que casa com o filtro.
            'average_label' => $partial ? 'Média por nota (filtro)' : 'Ticket médio',
            'is_partial' => $partial,
            'delta_pct' => $this->deltaPct($userId, $f, $total),
        ];
    }

    /** Variação do total contra o período anterior de mesma duração; null em "Tudo" ou sem gasto anterior. */
    private function deltaPct(int $userId, array $f, float $current): ?float
    {
        if (Period::isAllTime($f['start_date'])) {
            return null;
        }

        $previous = Period::previous($f['start_date'], $f['end_date']);

        $total = $this->isPartial($f)
            ? $this->itemsBase($userId, $f, $previous)->sum('invoices_items.total_price')
            : $this->invoicesBase($userId, $f, $previous)->sum('invoices.total_amount');

        return Period::deltaPct($current, (float) $total);
    }

    private function categoryBreakdown(int $userId, array $f): Collection
    {
        return $this->itemsBase($userId, $f)
            ->select(
                DB::raw("COALESCE(categories.name, 'Sem categoria') as category_name"),
                DB::raw("COALESCE(categories.color, '#94A3B8') as category_color"),
                DB::raw('SUM(invoices_items.total_price) as total')
            )
            ->groupBy('category_name', 'category_color')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Últimos 12 meses até o fim do período do relatório, com os filtros aplicados; meses sem gasto zerados.
     *
     * @return list<array{month: string, total: float}>
     */
    private function monthly(int $userId, array $f): array
    {
        $end = Carbon::parse($f['end_date']);
        $start = $end->copy()->subMonths(11)->startOfMonth();
        $range = [$start->toDateString(), $end->copy()->endOfMonth()->toDateString()];

        $query = $this->isPartial($f)
            ? $this->itemsBase($userId, $f, $range)->selectRaw('substr(invoices.issued_at, 1, 7) as month, SUM(invoices_items.total_price) as total')
            : $this->invoicesBase($userId, $f, $range)->selectRaw('substr(invoices.issued_at, 1, 7) as month, SUM(invoices.total_amount) as total');

        $totals = $query->groupBy('month')->pluck('total', 'month');

        return collect(range(0, 11))->map(function (int $offset) use ($start, $totals) {
            $month = $start->copy()->addMonths($offset)->format('Y-m');

            return ['month' => $month, 'total' => (float) ($totals[$month] ?? 0)];
        })->all();
    }

    /**
     * Onde mais se gasta no período. Com um emissor filtrado não há o que comparar.
     *
     * @return list<array{issuer_id: ?int, name: string, total: float}>
     */
    private function byIssuer(int $userId, array $f): array
    {
        if ($f['issuer_id'] !== null) {
            return [];
        }

        $partial = $this->isPartial($f);
        $totalColumn = $partial ? 'invoices_items.total_price' : 'invoices.total_amount';

        $query = $partial
            ? $this->itemsBase($userId, $f)
            : $this->invoicesBase($userId, $f)
                ->leftJoin('issuers', 'issuers.id', '=', 'invoices.issuer_id')
                ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                    $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                        ->where('issuer_nicknames.user_id', '=', $userId);
                });

        return $query
            ->selectRaw("issuers.id as issuer_id, COALESCE(issuer_nicknames.nickname, issuers.name, '".self::NO_ISSUER."') as issuer_name, SUM({$totalColumn}) as total")
            ->groupBy('issuers.id', 'issuer_nicknames.nickname', 'issuers.name')
            ->orderByDesc('total')
            ->limit(self::TOP_ISSUERS)
            ->get()
            ->map(fn ($row) => ['issuer_id' => $row->issuer_id !== null ? (int) $row->issuer_id : null, 'name' => $row->issuer_name, 'total' => (float) $row->total])
            ->all();
    }

    // ─────────────────────────── CSV ───────────────────────────

    /** Escreve o CSV (UTF-8 com BOM, separador ";") lendo os itens em blocos — sem carregar tudo na memória. */
    public function streamCsv($handle, int $userId, array $filters): void
    {
        $this->writeCsv($handle, $this->itemsQuery($userId, $this->normalize($filters))->lazy(self::CSV_CHUNK));
    }

    /** CSV em texto (anexo de e-mail), com os mesmos filtros. */
    public function renderCsvFor(int $userId, array $filters): string
    {
        $handle = fopen('php://temp', 'w+');
        $this->streamCsv($handle, $userId, $filters);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /** CSV a partir dos itens já carregados em `$data['items']`. */
    public function renderCsv(array $data): string
    {
        $handle = fopen('php://temp', 'w+');
        $this->writeCsv($handle, $data['items']);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function writeCsv($handle, iterable $items): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Data', 'Emissor', 'Produto', 'Categoria', 'Qtd', 'Unidade', 'Preço Unit.', 'Total'], ';', '"', '');

        foreach ($items as $item) {
            fputcsv($handle, [
                Carbon::parse($item->issued_at)->format('d/m/Y'),
                $this->csvText($item->issuer_name),
                $this->csvText($item->description),
                $this->csvText($item->category_name ?? 'Sem categoria'),
                number_format($item->quantity, 4, ',', '.'),
                $this->csvText($item->unit),
                number_format($item->unit_price, 2, ',', '.'),
                number_format($item->total_price, 2, ',', '.'),
            ], ';', '"', ''); // escape vazio: o padrão está descontinuado no PHP 8.4
        }
    }

    /**
     * Descrições vêm da NFC-e (terceiros): célula que começa com = + - @ (ou tab/CR) seria executada como fórmula
     * ao abrir no Excel. O apóstrofo à frente força texto (padrão OWASP contra CSV injection).
     */
    private function csvText(?string $value): string
    {
        $value ??= '';

        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }
}
