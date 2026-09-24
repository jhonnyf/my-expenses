<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\InvoiceItem;
use App\Models\RecurringDismissal;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Produtos comprados com frequência e quando comprar de novo.
 *
 * - Só entram notas autorizadas dos últimos HISTORY_MONTHS meses, com preço válido (> 0). Um produto é recorrente com pelo
 *   menos MIN_PURCHASE_DAYS DIAS de compra distintos (várias notas no mesmo dia contam uma vez).
 * - O intervalo é a MEDIANA dos espaços entre os dias de compra; a frequência mensal é medida numa janela recente
 *   (no máximo RATE_WINDOW_DAYS), não entre a primeira e a última compra — rajadas não inflam mais o número.
 * - Produtos de unidades diferentes (KG, UN) são recorrências separadas.
 * - Situação: `late` (atrasado), `due` (na hora), `soon` (chega logo), `ok`, ou `inactive` quando ficou sem comprar
 *   por mais de 3 intervalos (no mínimo 30 e no máximo 180 dias).
 * - O melhor mercado é o de menor preço ATUAL entre os que o usuário frequenta (compra mais recente de cada um,
 *   dentro de FRESH_DAYS) — mesma regra da busca da Lista de Compras e dos comparativos de Preços.
 */
class RecurringPurchaseService
{
    public const MIN_PURCHASE_DAYS = 3;

    public const HISTORY_MONTHS = 24;

    public const RATE_WINDOW_DAYS = 365;

    public const FRESH_DAYS = ShoppingListService::FRESH_DAYS;

    /** Da mais urgente para a menos urgente. */
    public const STATUSES = ['late', 'due', 'soon', 'ok', 'inactive'];

    public const SORTS = ['due', 'frequency', 'spend', 'saving', 'name'];

    private const LATE_FACTOR = 1.5;

    private const SOON_MAX_DAYS = 7;

    private const INACTIVE_MULTIPLIER = 3;

    private const INACTIVE_MIN_DAYS = 30;

    private const INACTIVE_MAX_DAYS = 180;

    public function __construct(
        private readonly ProductAliasService $aliasService,
        private readonly ShoppingListService $shoppingLists,
    ) {}

    /**
     * Todos os produtos recorrentes do usuário (inclusive inativos e ocultos), com situação e preços atuais.
     *
     * @return Collection<int, object>
     */
    public function getRecurringItems(int $userId, ?Carbon $now = null): Collection
    {
        $now ??= Carbon::now();
        $today = $now->copy()->startOfDay();

        $stats = $this->aggregate($userId, $now);

        if ($stats->isEmpty()) {
            return collect();
        }

        $names = $stats->pluck('description')->unique()->values()->all();
        $days = $this->purchaseDays($userId, $now, $names);
        $latest = $this->latestPerIssuer($userId, $now, $names);
        $dismissed = RecurringDismissal::where('user_id', $userId)->pluck('description')
            ->mapWithKeys(fn (string $description) => [$this->key($description, '') => true]);
        $freshCutoff = $now->copy()->subDays(self::FRESH_DAYS);

        return $stats->map(function ($row) use ($days, $latest, $dismissed, $today, $now, $freshCutoff) {
            $key = $this->key($row->description, $row->unit);
            $purchaseDays = $days[$key] ?? [];

            // Sem pelo menos dois dias não há intervalo a calcular (não deve ocorrer; evita quebrar a tela toda).
            if (count($purchaseDays) < 2) {
                return null;
            }

            return $this->buildItem($row, $purchaseDays, $latest[$key] ?? collect(), $dismissed->has($this->key($row->description, '')), $today, $now, $freshCutoff);
        })->filter()->values();
    }

    /**
     * @param  array{status?: string, q?: string, sort?: string, dismissed?: bool}  $filters
     * @return Collection<int, object>
     */
    public function filter(Collection $items, array $filters = []): Collection
    {
        $status = $filters['status'] ?? 'active';
        $term = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $onlyDismissed = (bool) ($filters['dismissed'] ?? false);

        $filtered = $items
            ->filter(fn ($item) => $item->dismissed === $onlyDismissed)
            ->filter(fn ($item) => match ($status) {
                'all' => true,
                'active' => $item->status !== 'inactive',
                default => $item->status === $status,
            })
            ->when($term !== '', fn (Collection $collection) => $collection->filter(fn ($item) => str_contains(mb_strtolower($item->description), $term)));

        $sorted = match ($filters['sort'] ?? 'due') {
            'frequency' => $filtered->sortByDesc('purchases_per_month'),
            'spend' => $filtered->sortByDesc(fn ($item) => $item->avg_spend * $item->purchases_per_month),
            'saving' => $filtered->sortByDesc('estimated_saving_per_month'),
            'name' => $filtered->sortBy(fn ($item) => mb_strtolower($item->description)),
            default => $filtered->sortBy([
                [fn ($item) => array_search($item->status, self::STATUSES, true), 'asc'],
                ['days_until_due', 'asc'],
                [fn ($item) => mb_strtolower($item->description), 'asc'],
            ]),
        };

        return $sorted->values();
    }

    /**
     * Números do topo da tela: só o que está ativo e não foi ocultado.
     *
     * @return array{products: int, due_count: int, monthly_total: float, potential_saving: float, inactive_count: int, dismissed_count: int}
     */
    public function summary(Collection $items): array
    {
        $visible = $items->reject(fn ($item) => $item->dismissed);
        $active = $visible->reject(fn ($item) => $item->status === 'inactive');

        return [
            'products' => $active->count(),
            'due_count' => $active->filter(fn ($item) => $item->is_due)->count(),
            'monthly_total' => round($active->sum(fn ($item) => $item->avg_spend * $item->purchases_per_month), 2),
            'potential_saving' => round($active->sum('estimated_saving_per_month'), 2),
            'inactive_count' => $visible->count() - $active->count(),
            'dismissed_count' => $items->filter(fn ($item) => $item->dismissed)->count(),
        ];
    }

    public function dismiss(int $userId, string $description): void
    {
        RecurringDismissal::firstOrCreate(['user_id' => $userId, 'description' => $description]);
    }

    public function restore(int $userId, string $description): void
    {
        RecurringDismissal::where('user_id', $userId)->where('description', $description)->delete();
    }

    /**
     * Cria uma lista de compras com o que está na hora (ou atrasado): quantidade típica, preço e mercado atuais.
     * Devolve null quando não há nada a repor.
     *
     * @return array{list: ShoppingList, count: int}|null
     */
    public function createReplenishmentList(User $user): ?array
    {
        $due = $this->filter($this->getRecurringItems($user->id), ['status' => 'active'])->filter(fn ($item) => $item->is_due);

        if ($due->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($user, $due) {
            $list = $this->shoppingLists->createList($user->id, 'Reposição '.Carbon::now()->format('d/m/Y'));

            $due->each(function ($item) use ($list) {
                $source = $item->best_issuer ?? $item->last_issuer;

                $this->shoppingLists->addItem($list, [
                    'description' => $item->description,
                    'unit' => $item->unit !== '' ? $item->unit : null,
                    'unit_price' => $source?->price ?? $item->last_price,
                    'issuer_id' => $source?->issuer_id,
                    'quantity' => $item->suggested_quantity,
                ]);
            });

            return ['list' => $list, 'count' => $due->count()];
        });
    }

    /**
     * Adiciona um produto recorrente a uma lista do usuário (ou a uma lista nova quando `$list` é null).
     *
     * @param  array{description: string, unit?: ?string, unit_price?: mixed, issuer_id?: ?int, quantity?: int}  $data
     * @return array{item: ShoppingListItem, list: ShoppingList, merged: bool}
     */
    public function addToList(User $user, ?ShoppingList $list, array $data): array
    {
        $list ??= $this->shoppingLists->createList($user->id, null);
        $data['quantity'] = $data['quantity'] ?? 1;

        return $this->shoppingLists->addItem($list, $data) + ['list' => $list];
    }

    // ─────────────────────────── Cálculo ───────────────────────────

    /**
     * Chave de junção entre as consultas. O MySQL agrupa sem diferenciar maiúsculas/acentos (collation *_ci) e devolve
     * uma variante qualquer do texto em cada linha ("LEITE" num dia, "leite" no outro); comparar as strings cruas
     * dividiria um mesmo produto em vários e deixaria dias de compra de fora.
     */
    private function key(string $description, string $unit): string
    {
        return mb_strtolower(Str::ascii(trim($description))).'|'.mb_strtolower(trim($unit));
    }

    /** Itens do usuário na janela do histórico, com nome canônico e preço válido. */
    private function base(int $userId, Carbon $now): Builder
    {
        $query = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->where('invoices.status', InvoiceStatus::Authorized)
            ->where('invoices.issued_at', '>=', $now->copy()->subMonths(self::HISTORY_MONTHS))
            ->where('invoices_items.unit_price', '>', 0);
        $this->aliasService->joinCanonicalNames($query, $userId);

        return $query;
    }

    private function nameSql(): string
    {
        return $this->aliasService->canonicalNameSql();
    }

    private function unitSql(): string
    {
        return "COALESCE(NULLIF(TRIM(invoices_items.unit), ''), '')";
    }

    private function aggregate(int $userId, Carbon $now): Collection
    {
        $name = $this->nameSql();
        $unit = $this->unitSql();

        return $this->base($userId, $now)
            ->selectRaw("{$name} as description")
            ->selectRaw("{$unit} as unit")
            ->selectRaw('COUNT(DISTINCT invoices.id) as invoice_count')
            ->selectRaw('COUNT(DISTINCT invoices.issuer_id) as issuer_count')
            ->selectRaw('AVG(invoices_items.unit_price) as avg_price')
            ->selectRaw('MIN(invoices_items.unit_price) as min_price')
            ->selectRaw('MAX(invoices_items.unit_price) as max_price')
            ->selectRaw('SUM(invoices_items.quantity) as total_quantity')
            ->selectRaw('SUM(invoices_items.total_price) as total_spent')
            ->groupByRaw('1, 2') // posição no select: o MySQL/MariaDB do host não reconhece a expressão repetida sob ONLY_FULL_GROUP_BY
            ->havingRaw('COUNT(DISTINCT substr(invoices.issued_at, 1, 10)) >= ?', [self::MIN_PURCHASE_DAYS])
            ->get();
    }

    /**
     * Dias de compra distintos (Y-m-d, crescentes) de cada produto.
     *
     * @param  list<string>  $names
     * @return Collection<string, list<string>>
     */
    private function purchaseDays(int $userId, Carbon $now, array $names): Collection
    {
        $name = $this->nameSql();
        $unit = $this->unitSql();

        return $this->base($userId, $now)
            ->whereIn(DB::raw($name), $names)
            ->selectRaw("{$name} as description")
            ->selectRaw("{$unit} as unit")
            ->selectRaw('substr(invoices.issued_at, 1, 10) as day')
            ->groupByRaw('1, 2, 3')
            ->orderBy('day')
            ->get()
            ->groupBy(fn ($row) => $this->key($row->description, $row->unit))
            ->map(fn (Collection $rows) => $rows->pluck('day')->unique()->sort()->values()->all());
    }

    /**
     * A compra mais recente de cada produto em cada mercado que o usuário frequenta.
     *
     * @param  list<string>  $names
     * @return Collection<string, Collection<int, object>>
     */
    private function latestPerIssuer(int $userId, Carbon $now, array $names): Collection
    {
        $name = $this->nameSql();
        $unit = $this->unitSql();

        $inner = $this->base($userId, $now)
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            })
            ->whereIn(DB::raw($name), $names)
            ->selectRaw("{$name} as description")
            ->selectRaw("{$unit} as unit")
            ->selectRaw('issuers.id as issuer_id')
            ->selectRaw('COALESCE(issuer_nicknames.nickname, issuers.name) as issuer_name')
            ->selectRaw('invoices_items.unit_price as price')
            ->selectRaw('invoices.issued_at as issued_at')
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY {$name}, {$unit}, invoices.issuer_id ORDER BY invoices.issued_at DESC, invoices_items.id DESC) as rn");

        return InvoiceItem::query()->fromSub($inner, 'latest')->where('rn', 1)->get()
            ->groupBy(fn ($row) => $this->key($row->description, $row->unit));
    }

    private function buildItem(object $row, array $purchaseDays, Collection $latestByIssuer, bool $dismissed, Carbon $today, Carbon $now, Carbon $freshCutoff): object
    {
        $dates = array_map(fn (string $day) => Carbon::parse($day)->startOfDay(), $purchaseDays);
        $first = $dates[0];
        $last = end($dates);

        $interval = $this->medianInterval($dates);
        $daysSinceLast = (int) $last->diffInDays($today);
        $daysUntilDue = $interval - $daysSinceLast;

        // Frequência numa janela recente, para uma rajada antiga não inflar o número.
        $windowDays = (int) min(self::RATE_WINDOW_DAYS, max(30, $first->diffInDays($today)));
        $recentCount = count(array_filter($dates, fn (Carbon $date) => $date->gte($today->copy()->subDays($windowDays))));
        $perMonth = round(($recentCount / $windowDays) * 30, 1);

        $invoiceCount = max((int) $row->invoice_count, 1);
        $suggestedQuantity = max(1, (int) round((float) $row->total_quantity / $invoiceCount));
        $avgSpend = (float) $row->total_spent / $invoiceCount;

        $latestRows = $latestByIssuer->map(fn ($latest) => (object) [
            'issuer_id' => (int) $latest->issuer_id,
            'issuer_name' => $latest->issuer_name,
            'price' => (float) $latest->price,
            'issued_at' => $latest->issued_at,
            'is_stale' => Carbon::parse($latest->issued_at)->lt($freshCutoff),
        ]);

        $lastIssuer = $latestRows->sortByDesc('issued_at')->first();
        $best = $latestRows->sortBy([['is_stale', 'asc'], ['price', 'asc']])->first();
        $lastPrice = $lastIssuer?->price ?? (float) $row->avg_price;

        $saving = ($best !== null && ! $best->is_stale && $best->price < $lastPrice)
            ? ($lastPrice - $best->price) * $suggestedQuantity * $perMonth
            : 0.0;

        $status = $this->status($interval, $daysSinceLast, $daysUntilDue);

        return (object) [
            'description' => $row->description,
            'unit' => $row->unit,
            'purchase_count' => count($dates),
            'issuer_count' => (int) $row->issuer_count,
            'avg_price' => (float) $row->avg_price,
            'min_price' => (float) $row->min_price,
            'max_price' => (float) $row->max_price,
            'last_price' => $lastPrice,
            'last_purchased_at' => $last->toDateString(),
            'first_purchased_at' => $first->toDateString(),
            'avg_interval_days' => $interval,
            'interval_days' => $interval,
            'purchases_per_month' => $perMonth,
            'days_since_last' => $daysSinceLast,
            'days_until_due' => $daysUntilDue,
            'next_expected_at' => $last->copy()->addDays($interval)->toDateString(),
            'status' => $status,
            'is_due' => in_array($status, ['due', 'late'], true),
            'suggested_quantity' => $suggestedQuantity,
            'avg_spend' => round($avgSpend, 2),
            'best_issuer' => $best,
            'last_issuer' => $lastIssuer,
            'estimated_saving_per_month' => round($saving, 2),
            'dismissed' => $dismissed,
        ];
    }

    /**
     * Mediana dos espaços (em dias) entre dias de compra consecutivos; pelo menos 1.
     *
     * @param  list<Carbon>  $dates
     */
    private function medianInterval(array $dates): int
    {
        $gaps = [];
        for ($i = 1; $i < count($dates); $i++) {
            $gaps[] = (int) $dates[$i - 1]->diffInDays($dates[$i]);
        }

        sort($gaps);
        $middle = intdiv(count($gaps), 2);
        $median = count($gaps) % 2 === 1 ? $gaps[$middle] : ($gaps[$middle - 1] + $gaps[$middle]) / 2;

        return max(1, (int) round($median));
    }

    private function status(int $interval, int $daysSinceLast, int $daysUntilDue): string
    {
        $inactiveAfter = min(max($interval * self::INACTIVE_MULTIPLIER, self::INACTIVE_MIN_DAYS), self::INACTIVE_MAX_DAYS);

        return match (true) {
            $daysSinceLast > $inactiveAfter => 'inactive',
            $daysSinceLast >= $interval * self::LATE_FACTOR => 'late',
            $daysUntilDue <= 0 => 'due',
            $daysUntilDue <= min(self::SOON_MAX_DAYS, max(1, (int) round($interval * 0.25))) => 'soon',
            default => 'ok',
        };
    }
}
