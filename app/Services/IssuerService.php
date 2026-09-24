<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use App\Models\User;
use App\Support\FullTextQuery;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Todo acesso a emissor parte das notas do usuário: um emissor sem nenhuma nota
 * dele não é listado, aberto, favoritado nem apelidado (404).
 */
class IssuerService
{
    private const PER_PAGE = 15;

    private const INVOICES_PER_PAGE = 15;

    private const TOP_PRODUCTS = 5;

    private const TICKET_TREND_WINDOW = 3;

    public function __construct(private readonly ProductAliasService $aliasService) {}

    /**
     * @param  array{q?: string, sort?: string, city?: string, favorites?: bool}  $filters
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        // Subqueries correlacionadas de propósito: com LIMIT elas rodam só para as linhas da página.
        // Medido com 50 mil notas / 2 mil emissores: 76 ms contra 392 ms de um GROUP BY único
        // (que agrega todas as notas antes de ordenar e paginar).
        $query = Issuer::query()
            ->select('issuers.*')
            ->whereHas('invoices', fn ($q) => $q->where('user_id', $user->id))
            ->leftJoin('issuer_nicknames', function ($join) use ($user) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $user->id);
            })
            ->leftJoin('favorite_issuers', function ($join) use ($user) {
                $join->on('favorite_issuers.issuer_id', '=', 'issuers.id')
                    ->where('favorite_issuers.user_id', '=', $user->id);
            })
            ->addSelect('issuer_nicknames.nickname as nickname')
            ->withCount(['invoices as purchase_count' => fn ($q) => $q->where('user_id', $user->id)])
            ->withSum(['invoices as total_spent' => fn ($q) => $q->where('user_id', $user->id)], 'total_amount')
            ->withMax(['invoices as last_purchase_at' => fn ($q) => $q->where('user_id', $user->id)], 'issued_at');

        $search = $filters['q'] ?? '';

        if ($search !== '') {
            // Aceita CNPJ formatado na busca; sem FULLTEXT em issuers.name, o LIKE roda só no
            // conjunto de emissores do próprio usuário (já restrito acima).
            $term = preg_match('/^[\d.\/\-\s]+$/', $search) ? preg_replace('/\D/', '', $search) : $search;

            FullTextQuery::applyOr($query, $term, [], ['issuers.name', 'issuers.cnpj', 'issuer_nicknames.nickname']);
        }

        if (($filters['city'] ?? '') !== '') {
            $query->where('issuers.city', $filters['city']);
        }

        if ($filters['favorites'] ?? false) {
            $query->whereNotNull('favorite_issuers.issuer_id');
        }

        $this->applySort($query, $filters['sort'] ?? 'name');

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Ordenar por total/visitas/última compra calcula as subqueries para todos os emissores
     * do usuário (não só a página) — aceitável para a ordem escolhida; o padrão (favoritos +
     * nome) continua barato, ver comentário em paginateForUser.
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'spent' => $query->orderByDesc('total_spent'),
            'visits' => $query->orderByDesc('purchase_count'),
            'last' => $query->orderByDesc('last_purchase_at'),
            default => $query->orderByRaw('favorite_issuers.issuer_id IS NOT NULL DESC'),
        };

        $query->orderBy('issuers.name');
    }

    /**
     * Emissores para um <select> (id + nome de exibição: apelido ou nome oficial), incluindo os que só
     * têm notas pendentes — o filtro da lista de compras enxerga também essas notas.
     *
     * @return list<array{id: int, name: string}>
     */
    public function optionsForUser(User $user): array
    {
        return Issuer::query()
            ->select('issuers.id', 'issuers.name')
            ->leftJoin('issuer_nicknames', function ($join) use ($user) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $user->id);
            })
            ->addSelect('issuer_nicknames.nickname as nickname')
            ->whereHas('invoices', fn ($q) => $q->includingUnauthorized()->where('user_id', $user->id))
            ->orderByRaw('COALESCE(issuer_nicknames.nickname, issuers.name)')
            ->get()
            ->map(fn (Issuer $issuer) => ['id' => $issuer->id, 'name' => $issuer->nickname ?: $issuer->name])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function citiesForUser(User $user): array
    {
        return Issuer::whereHas('invoices', fn ($q) => $q->where('user_id', $user->id))
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->all();
    }

    /**
     * @return array{total_spent: float, invoices_count: int, average_ticket: float, top_issuer: ?array{name: string, visits: int}}
     */
    public function summaryForUser(User $user): array
    {
        $totals = Invoice::where('user_id', $user->id)
            ->selectRaw('COUNT(*) as invoices_count, COALESCE(SUM(total_amount), 0) as total_spent')
            ->first();

        $top = Invoice::where('user_id', $user->id)
            ->whereNotNull('issuer_id')
            ->selectRaw('issuer_id, COUNT(*) as visits')
            ->groupBy('issuer_id')
            ->orderByDesc('visits')
            ->first();

        $topIssuer = null;
        if ($top !== null) {
            $issuer = Issuer::find($top->issuer_id);
            $nickname = $issuer->nicknames()->where('user_id', $user->id)->value('nickname');
            $topIssuer = ['name' => $nickname ?: $issuer->name, 'visits' => (int) $top->visits];
        }

        $count = (int) $totals->invoices_count;
        $total = (float) $totals->total_spent;

        return [
            'total_spent' => $total,
            'invoices_count' => $count,
            'average_ticket' => $count > 0 ? $total / $count : 0.0,
            'top_issuer' => $topIssuer,
        ];
    }

    public function findForUser(User $user, int $id): Issuer
    {
        // includingUnauthorized: o app abre o emissor a partir de uma nota ainda pendente, que já é do usuário.
        return Issuer::whereHas('invoices', fn ($q) => $q->includingUnauthorized()->where('user_id', $user->id))
            ->findOrFail($id);
    }

    /**
     * @return array{issuer: Issuer, invoices: LengthAwarePaginator, stats: object, insights: array, is_favorite: bool, nickname: ?string}
     */
    public function detailForUser(User $user, int $id, ?string $invoiceSearch = null): array
    {
        $issuer = $this->findForUser($user, $id);

        $invoices = $issuer->invoices()
            ->where('user_id', $user->id)
            ->select(['id', 'issuer_id', 'number', 'series', 'issued_at', 'total_amount'])
            ->withCount('items')
            ->when(trim((string) $invoiceSearch) !== '', fn ($q) => $q->where('number', 'like', '%'.trim($invoiceSearch).'%'))
            ->latest('issued_at')
            ->paginate(self::INVOICES_PER_PAGE)
            ->withQueryString();

        $stats = $issuer->invoices()
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(total_amount), 0) as total_sum, MIN(issued_at) as first_at, MAX(issued_at) as last_at')
            ->first();

        return [
            'issuer' => $issuer,
            'invoices' => $invoices,
            'stats' => $stats,
            'insights' => $this->insights($user, $issuer, $stats),
            'is_favorite' => $user->favoriteIssuers()->where('issuers.id', $id)->exists(),
            'nickname' => $issuer->nicknames()->where('user_id', $user->id)->value('nickname'),
        ];
    }

    /**
     * @return array{
     *     average_ticket: float,
     *     ticket_trend_pct: ?float,
     *     visit_interval_days: ?int,
     *     monthly: list<array{month: string, total: float}>,
     *     top_products: list<array{name: string, purchases: int, total: float, average_price: float, last_price: float, variation_pct: ?float}>,
     *     categories: list<array{name: string, color: string, total: float, share: float}>
     * }
     */
    private function insights(User $user, Issuer $issuer, object $stats): array
    {
        $count = (int) $stats->total_count;

        return [
            'average_ticket' => $count > 0 ? round((float) $stats->total_sum / $count, 2) : 0.0,
            'ticket_trend_pct' => $this->ticketTrendPct($user, $issuer),
            'visit_interval_days' => $this->visitIntervalDays($stats),
            'monthly' => $this->monthlySpending($user, $issuer),
            'top_products' => $this->topProducts($user, $issuer),
            'categories' => $this->spendingByCategory($user, $issuer),
        ];
    }

    /**
     * Ticket médio das compras mais recentes vs. o das imediatamente anteriores (metade/metade das últimas
     * 2×janela notas). Precisa de ao menos 4 notas para a comparação fazer sentido; abaixo disso, null.
     */
    private function ticketTrendPct(User $user, Issuer $issuer): ?float
    {
        $totals = $issuer->invoices()
            ->where('user_id', $user->id)
            ->latest('issued_at')
            ->limit(self::TICKET_TREND_WINDOW * 2)
            ->pluck('total_amount')
            ->map(fn ($total) => (float) $total);

        if ($totals->count() < 4) {
            return null;
        }

        $half = intdiv($totals->count(), 2);
        $recent = $totals->take($half)->avg();
        $previous = $totals->slice($half, $half)->avg();

        return $previous > 0 ? round((($recent - $previous) / $previous) * 100, 1) : null;
    }

    private function visitIntervalDays(object $stats): ?int
    {
        if ($stats->total_count < 2 || ! $stats->first_at || ! $stats->last_at) {
            return null;
        }

        $days = Carbon::parse($stats->first_at)->diffInDays(Carbon::parse($stats->last_at), true);

        return max(1, (int) round($days / ($stats->total_count - 1)));
    }

    /**
     * Últimos 12 meses (inclusive o atual), com meses sem compra zerados para o gráfico não "pular".
     */
    private function monthlySpending(User $user, Issuer $issuer): array
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth();

        $totals = $issuer->invoices()
            ->where('user_id', $user->id)
            ->where('issued_at', '>=', $start)
            ->select(DB::raw('substr(issued_at, 1, 7) as month'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        return collect(range(0, 11))->map(function (int $offset) use ($start, $totals) {
            $month = $start->copy()->addMonths($offset)->format('Y-m');

            return ['month' => $month, 'total' => (float) ($totals[$month] ?? 0)];
        })->all();
    }

    private function itemsOf(User $user, Issuer $issuer)
    {
        return InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $user->id)
            ->where('invoices.issuer_id', $issuer->id);
    }

    private function topProducts(User $user, Issuer $issuer): array
    {
        $name = $this->aliasService->canonicalNameSql();

        $top = $this->aliasService->joinCanonicalNames($this->itemsOf($user, $issuer), $user->id)
            ->selectRaw("{$name} as name, COUNT(*) as purchases, SUM(invoices_items.total_price) as total, AVG(invoices_items.unit_price) as average_price")
            ->groupBy(DB::raw($name))
            ->orderByDesc('total')
            ->limit(self::TOP_PRODUCTS)
            ->get();

        if ($top->isEmpty()) {
            return [];
        }

        // Último preço e variação vs. a compra anterior, só para os produtos exibidos.
        $recent = $this->aliasService->joinCanonicalNames($this->itemsOf($user, $issuer), $user->id)
            ->whereIn(DB::raw($name), $top->pluck('name'))
            ->selectRaw("{$name} as name, invoices_items.unit_price")
            ->orderByDesc('invoices.issued_at')
            ->get()
            ->groupBy('name');

        return $top->map(function ($row) use ($recent) {
            $prices = $recent[$row->name] ?? collect();
            $last = (float) ($prices[0]->unit_price ?? 0);
            $previous = (float) ($prices[1]->unit_price ?? 0);

            return [
                'name' => $row->name,
                'purchases' => (int) $row->purchases,
                'total' => (float) $row->total,
                'average_price' => round((float) $row->average_price, 2),
                'last_price' => $last,
                'variation_pct' => $previous > 0 ? round((($last - $previous) / $previous) * 100, 1) : null,
            ];
        })->all();
    }

    private function spendingByCategory(User $user, Issuer $issuer): array
    {
        $rows = $this->itemsOf($user, $issuer)
            ->leftJoin('categories', 'categories.id', '=', 'invoices_items.category_id')
            ->selectRaw("COALESCE(categories.name, 'Sem categoria') as category_name, COALESCE(categories.color, '#94A3B8') as category_color, SUM(invoices_items.total_price) as total")
            ->groupBy('category_name', 'category_color')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $sum = (float) $rows->sum('total');

        return $rows->map(fn ($row) => [
            'name' => $row->category_name,
            'color' => $row->category_color,
            'total' => (float) $row->total,
            'share' => $sum > 0 ? round((float) $row->total / $sum * 100, 1) : 0.0,
        ])->all();
    }

    public function toggleFavorite(User $user, int $id): bool
    {
        $issuer = $this->findForUser($user, $id);

        return ! empty($user->favoriteIssuers()->toggle($issuer->id)['attached']);
    }

    /**
     * @return array{nickname: ?string, display_name: string}
     */
    public function updateNickname(User $user, int $id, ?string $nickname): array
    {
        $issuer = $this->findForUser($user, $id);
        $nickname = trim((string) $nickname);

        if ($nickname === '') {
            $issuer->nicknames()->where('user_id', $user->id)->delete();
        } else {
            IssuerNickname::updateOrCreate(
                ['user_id' => $user->id, 'issuer_id' => $issuer->id],
                ['nickname' => $nickname]
            );
        }

        return [
            'nickname' => $nickname !== '' ? $nickname : null,
            'display_name' => $nickname !== '' ? $nickname : $issuer->name,
        ];
    }
}
