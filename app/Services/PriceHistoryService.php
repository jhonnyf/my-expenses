<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\ProductAlias;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PriceHistoryService
{
    public function __construct(private readonly ProductAliasService $aliasService) {}

    /**
     * Busca produtos no histórico de compras do próprio usuário. O termo
     * também casa contra apelidos de produto criados por QUALQUER outro
     * usuário (ver ProductAliasService::otherUsersAliasLikeExists) — o nome
     * "oficial" do resultado (`description`, usado pra abrir o timeline)
     * continua sendo o apelido do próprio usuário, ou a description bruta
     * na ausência dele.
     *
     * `display_description` (só exibição) traz o apelido quando existir,
     * senão a description bruta. `official_description` vem preenchido só
     * quando o front deve mostrar o nome oficial na NF numa segunda linha:
     * quando o único apelido disponível pra esse produto veio de outro
     * usuário — o próprio usuário nunca apelidou, então o nome oficial ajuda
     * a confirmar do que se trata antes de abrir o histórico.
     */
    public function search(string $query, int $userId): Collection
    {
        $itemsQuery = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->leftJoin('product_aliases', function ($join) use ($userId) {
                $join->on('product_aliases.description', '=', 'invoices_items.description')
                    ->where('product_aliases.user_id', '=', $userId);
            });
        $this->aliasService->joinCommunityCanonicalName($itemsQuery, $userId);

        $items = $itemsQuery
            ->where('invoices.user_id', $userId)
            ->where(function ($q) use ($query, $userId) {
                $q->where('invoices_items.description', 'like', "%{$query}%")
                    ->orWhere('product_aliases.canonical_name', 'like', "%{$query}%")
                    ->orWhereExists($this->aliasService->otherUsersAliasLikeExists($query, $userId));
            })
            ->select(
                DB::raw('COALESCE(product_aliases.canonical_name, invoices_items.description) as description'),
                DB::raw('MIN(invoices_items.description) as raw_description'),
                DB::raw('MIN(product_aliases.canonical_name) as own_canonical_name'),
                DB::raw('MIN(community_aliases.canonical_name) as community_canonical_name'),
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw('MIN(invoices_items.unit_price) as min_price'),
                DB::raw('MAX(invoices_items.unit_price) as max_price'),
                DB::raw('AVG(invoices_items.unit_price) as avg_price'),
                DB::raw('MAX(invoices.issued_at) as last_purchased_at')
            )
            ->groupBy(DB::raw('COALESCE(product_aliases.canonical_name, invoices_items.description)'))
            ->orderByDesc('purchase_count')
            ->limit(20)
            ->get();

        $items->each(function (InvoiceItem $item) {
            if ($item->own_canonical_name !== null) {
                $item->display_description = $item->own_canonical_name;
                $item->official_description = null;
            } elseif ($item->community_canonical_name !== null && $item->community_canonical_name !== $item->raw_description) {
                $item->display_description = $item->community_canonical_name;
                $item->official_description = $item->raw_description;
            } else {
                $item->display_description = $item->raw_description;
                $item->official_description = null;
            }

            unset($item->raw_description, $item->own_canonical_name, $item->community_canonical_name);
        });

        return $items;
    }

    /** Pontos individuais devolvidos (os mais recentes); o resumo e a série mensal cobrem o histórico inteiro. */
    public const TIMELINE_LIMIT = 200;

    private const MONTHLY_MONTHS = 24;

    private const MEDIAN_SAMPLE = 5000;

    /**
     * Histórico de preços do PRÓPRIO usuário para um produto.
     *
     * `$description` pode ser um nome canônico (várias descrições originais unificadas via ProductAlias) ou uma
     * descrição bruta ainda não unificada. `$unit` restringe a uma unidade (KG, UN...): preços de unidades
     * diferentes não se comparam.
     *
     * @return array{timeline: Collection, descriptions: Collection, units: list<array{unit: string, sample_count: int}>, unit: ?string, total_entries: int, truncated: bool, monthly: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function getTimeline(string $description, int $userId, ?string $unit = null): array
    {
        $matchDescriptions = ProductAlias::where('user_id', $userId)
            ->where('canonical_name', $description)
            ->pluck('description');

        if ($matchDescriptions->isEmpty()) {
            $matchDescriptions = collect([$description]);
        }

        $base = fn () => InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->leftJoin('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->where('invoices.user_id', $userId)
            ->whereIn('invoices_items.description', $matchDescriptions)
            ->where('invoices_items.unit_price', '>', 0);

        $forUnit = fn () => $base()->when(filled($unit), fn ($query) => $query->where('invoices_items.unit', $unit));

        $units = $base()
            ->selectRaw("COALESCE(invoices_items.unit, '') as unit")
            ->selectRaw('COUNT(*) as sample_count')
            ->groupBy(DB::raw("COALESCE(invoices_items.unit, '')"))
            ->orderByDesc('sample_count')
            ->get()
            ->map(fn ($row) => ['unit' => (string) $row->unit, 'sample_count' => (int) $row->sample_count])
            ->all();

        // Os mais recentes (e não os mais antigos): com muito histórico, o que interessa é o preço de agora.
        $timeline = $forUnit()
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            })
            ->select(
                'invoices_items.unit_price',
                'invoices_items.quantity',
                'invoices_items.unit',
                'invoices.issued_at',
                'issuers.id as issuer_id'
            )
            ->selectRaw("COALESCE(issuer_nicknames.nickname, issuers.name, 'Emissor não identificado') as issuer_name")
            ->orderByDesc('invoices.issued_at')
            ->orderByDesc('invoices_items.id')
            ->limit(self::TIMELINE_LIMIT)
            ->get()
            ->reverse()
            ->values();

        $totals = $forUnit()
            ->selectRaw('COUNT(*) as entries, MIN(invoices_items.unit_price) as min_price, MAX(invoices_items.unit_price) as max_price, AVG(invoices_items.unit_price) as avg_price')
            ->first();

        return [
            'timeline' => $timeline,
            'descriptions' => $matchDescriptions->values(),
            'units' => $units,
            'unit' => filled($unit) ? $unit : null,
            'total_entries' => (int) $totals->entries,
            'truncated' => (int) $totals->entries > $timeline->count(),
            'monthly' => $this->monthly($forUnit()),
            'summary' => $this->summary($forUnit, $totals),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(\Closure $forUnit, object $totals): array
    {
        $min = (float) ($totals->min_price ?? 0);
        $max = (float) ($totals->max_price ?? 0);

        $recent = $forUnit()->orderByDesc('invoices.issued_at')->orderByDesc('invoices_items.id')->limit(2)->pluck('invoices_items.unit_price');
        $last = $recent->isNotEmpty() ? (float) $recent[0] : null;
        $previous = $recent->count() > 1 ? (float) $recent[1] : null;

        $priceAgo = fn (int $days) => $forUnit()
            ->where('invoices.issued_at', '<=', Carbon::now()->subDays($days))
            ->orderByDesc('invoices.issued_at')->orderByDesc('invoices_items.id')
            ->value('invoices_items.unit_price');
        $ago30 = $priceAgo(30);
        $ago90 = $priceAgo(90);

        $change = fn (?float $from) => ($from !== null && $last !== null && $from > 0) ? round((($last - $from) / $from) * 100, 1) : null;
        $changePct = $change($previous);

        return [
            'min_price' => $min,
            'max_price' => $max,
            'avg_price' => round((float) ($totals->avg_price ?? 0), 2),
            'median_price' => $this->median($forUnit()->limit(self::MEDIAN_SAMPLE)->pluck('invoices_items.unit_price')),
            // Amplitude do histórico ((máx − mín) ÷ mín): NÃO é variação no tempo. Mantida por compatibilidade.
            'variation_pct' => round($min > 0 ? (($max - $min) / $min) * 100 : 0, 1),
            'spread_pct' => round($min > 0 ? (($max - $min) / $min) * 100 : 0, 1),
            'last_price' => $last,
            'previous_price' => $previous,
            'change_pct' => $changePct,
            'change_30d_pct' => $change($ago30 !== null ? (float) $ago30 : null),
            'change_90d_pct' => $change($ago90 !== null ? (float) $ago90 : null),
            'trend' => match (true) {
                $changePct === null => null,
                $changePct > 0 => 'up',
                $changePct < 0 => 'down',
                default => 'flat',
            },
        ];
    }

    private function median(Collection $prices): ?float
    {
        $sorted = $prices->map(fn ($price) => (float) $price)->sort()->values();

        if ($sorted->isEmpty()) {
            return null;
        }

        $middle = intdiv($sorted->count(), 2);

        return round($sorted->count() % 2 === 1 ? $sorted[$middle] : ($sorted[$middle - 1] + $sorted[$middle]) / 2, 2);
    }

    /**
     * Mínimo, média e máximo por mês (últimos 24 meses), só dos meses com compra.
     *
     * @return list<array{month: string, min: float, avg: float, max: float, count: int}>
     */
    private function monthly(Builder $query): array
    {
        return $query
            ->where('invoices.issued_at', '>=', Carbon::now()->subMonths(self::MONTHLY_MONTHS)->startOfMonth())
            ->selectRaw('substr(invoices.issued_at, 1, 7) as month, MIN(invoices_items.unit_price) as min_price, AVG(invoices_items.unit_price) as avg_price, MAX(invoices_items.unit_price) as max_price, COUNT(*) as entries')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'min' => (float) $row->min_price,
                'avg' => round((float) $row->avg_price, 2),
                'max' => (float) $row->max_price,
                'count' => (int) $row->entries,
            ])
            ->all();
    }
}
