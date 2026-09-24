<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use App\Support\DistanceCalculator;
use App\Support\FullTextQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShoppingListService
{
    private const RADIUS_KM = 15;

    private const SEARCH_LIMIT = 20;

    /** Preço mais recente com mais de 90 dias é "antigo": aparece depois dos atuais, mesmo que seja mais barato. */
    public const FRESH_DAYS = 90;

    private const DEFAULT_NAME_FORMAT = 'd/m/Y';

    private const SAVINGS_MAX_ITEMS = 30;

    public function __construct(private readonly ProductAliasService $aliasService) {}

    /**
     * Busca produtos para montar a lista de compras entre as notas fiscais
     * de todos os usuários (não só do usuário logado), para aproveitar
     * preços e produtos já cadastrados por outras pessoas. O nome canônico
     * exibido e o apelido de loja usados continuam sendo os do usuário que
     * busca, não os de quem originou a nota. O termo buscado, porém, também
     * casa contra apelidos de produto criados por QUALQUER outro usuário
     * (ver ProductAliasService::otherUsersAliasFullTextExists) — assim quem
     * nunca apelidou um item ainda o encontra pelo apelido que outra pessoa
     * já deu a ele.
     *
     * Cada resultado traz três nomes: `description` (o valor "oficial" do
     * item — apelido do próprio buscador, senão a description bruta — usado
     * ao adicionar o item à lista), `display_description` (nome principal
     * exibido na busca — apelido quando existir, senão a description bruta)
     * e `official_description` (nome oficial na NF, preenchido só quando o
     * front deve mostrá-lo numa segunda linha: item da comunidade, is_own =
     * 0, com apelido disponível de qualquer usuário e diferente do nome
     * oficial; null nos demais casos — item próprio, ou sem apelido nenhum).
     *
     * Filtro de localização (opcional):
     * - $filterCity/$filterState: o usuário escolheu explicitamente "outra cidade"
     *   pra comparar preços — compara por igualdade exata, sem raio.
     * - $userLatitude/$userLongitude: sem override, usa a localização do próprio
     *   usuário (perfil) e restringe a um raio real de 15km via Haversine.
     * - Se nada disso estiver disponível, não filtra (comportamento atual).
     */
    public function searchProducts(
        int $userId,
        string $query,
        Collection $favoriteIssuerIds,
        ?string $filterCity = null,
        ?string $filterState = null,
        ?float $userLatitude = null,
        ?float $userLongitude = null
    ): Collection {
        $itemsQuery = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            });
        $this->aliasService->joinCanonicalNames($itemsQuery, $userId);
        $this->aliasService->joinCommunityCanonicalName($itemsQuery, $userId);

        FullTextQuery::applyOr(
            $itemsQuery,
            $query,
            ['invoices_items.description', 'product_aliases.canonical_name'],
            existsCallbacks: [$this->aliasService->otherUsersAliasFullTextExists($query, $userId)]
        );

        $itemsQuery
            ->select(
                'invoices_items.description as raw_description',
                'product_aliases.canonical_name as own_canonical_name',
                'community_aliases.canonical_name as community_canonical_name',
                'invoices_items.unit_price',
                'invoices_items.unit',
                'invoices_items.code',
                'issuers.id as issuer_id',
                'invoices.issued_at'
            )
            ->selectRaw('COALESCE(issuer_nicknames.nickname, issuers.name) as issuer_name')
            ->selectRaw('CASE WHEN issuers.id IN ('.($favoriteIssuerIds->isNotEmpty() ? $favoriteIssuerIds->implode(',') : '0').') THEN 1 ELSE 0 END as is_favorite')
            ->selectRaw('CASE WHEN invoices.user_id = ? THEN 1 ELSE 0 END as is_own', [$userId]);

        $this->applyLocationFilter($itemsQuery, $filterCity, $filterState, $userLatitude, $userLongitude);

        // Só a compra mais recente de cada produto em cada mercado vale (é o preço de hoje); compras
        // repetidas do mesmo produto no mesmo mercado não ocupam mais as vagas da lista.
        $nameSql = $this->aliasService->canonicalNameSql();
        $itemsQuery->selectRaw("ROW_NUMBER() OVER (PARTITION BY {$nameSql}, issuers.id ORDER BY invoices.issued_at DESC, invoices_items.id DESC) as rn");

        $cutoff = Carbon::now()->subDays(self::FRESH_DAYS);

        // Do menor para o maior preço entre os atuais; os antigos (> FRESH_DAYS) vêm depois, também do menor ao maior.
        $items = InvoiceItem::query()
            ->fromSub($itemsQuery, 'latest')
            ->where('rn', 1)
            ->orderByRaw('CASE WHEN issued_at >= ? THEN 0 ELSE 1 END', [$cutoff->toDateTimeString()])
            ->orderBy('unit_price')
            ->orderByDesc('issued_at')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        $items->each(function (InvoiceItem $item) use ($cutoff) {
            $item->description = $item->own_canonical_name ?? $item->raw_description;
            [$item->display_description, $item->official_description] = $this->resolveDisplayNames($item);

            $item->is_stale = Carbon::parse($item->issued_at)->lt($cutoff);

            unset($item->own_canonical_name, $item->community_canonical_name, $item->raw_description, $item->rn);
        });

        return $items;
    }

    /**
     * `display_description`: o apelido quando existir, senão a description
     * bruta — nome principal exibido na busca.
     * `official_description`: nome oficial na NF, só preenchido quando o
     * front deve exibi-lo numa segunda linha (item da comunidade com algum
     * apelido disponível e diferente do nome oficial); null quando não há
     * nada a acrescentar (item próprio, ou item sem apelido nenhum).
     *
     * @return array{0: string, 1: ?string}
     */
    private function resolveDisplayNames(InvoiceItem $item): array
    {
        if ((int) $item->is_own === 1) {
            return [$item->description, null];
        }

        $nickname = $item->own_canonical_name ?? $item->community_canonical_name;

        if ($nickname === null || $nickname === $item->raw_description) {
            return [$item->raw_description, null];
        }

        return [$nickname, $item->raw_description];
    }

    private function applyLocationFilter($query, ?string $filterCity, ?string $filterState, ?float $userLatitude, ?float $userLongitude): void
    {
        $hasCity = $filterCity !== null && $filterState !== null;
        $hasCoordinates = $userLatitude !== null && $userLongitude !== null;

        // "Outra cidade": override explícito (ver controllers) — nunca vem acompanhado
        // de lat/lng. Igualdade exata, sem carve-out de "sem dado" nem raio.
        if ($hasCity && ! $hasCoordinates) {
            $query->where('issuers.city', $filterCity)->where('issuers.state', $filterState);

            return;
        }

        $hasRadius = $hasCoordinates && DistanceCalculator::isMySql();

        if (! $hasCity && ! $hasRadius) {
            return;
        }

        if ($hasRadius) {
            $distanceExpression = DistanceCalculator::mysqlHaversineExpression(
                'issuers.latitude',
                'issuers.longitude',
                $userLatitude,
                $userLongitude
            );
            $query->selectRaw("{$distanceExpression} as distance_km");
        }

        $query->where(function ($sub) use ($hasCity, $filterCity, $filterState, $hasRadius, $userLatitude, $userLongitude) {
            if ($hasCity) {
                $sub->orWhere(function ($q) use ($filterCity, $filterState) {
                    $q->where('issuers.city', $filterCity)->where('issuers.state', $filterState);
                });
            }

            if ($hasRadius) {
                $sub->orWhere(function ($q) use ($userLatitude, $userLongitude) {
                    $this->applyRadiusCondition($q, $userLatitude, $userLongitude);
                });
            }

            // Sem nenhum dado de localização (nem cidade/estado, nem lat/lng) — não dá
            // pra saber se está longe, então não escondemos o produto.
            $sub->orWhere(function ($q) {
                $q->where(fn ($q2) => $q2->whereNull('issuers.city')->orWhere('issuers.city', ''))
                    ->where(fn ($q2) => $q2->whereNull('issuers.state')->orWhere('issuers.state', ''))
                    ->whereNull('issuers.latitude')
                    ->whereNull('issuers.longitude');
            });
        });
    }

    private function applyRadiusCondition($query, float $userLatitude, float $userLongitude): void
    {
        $distanceExpression = DistanceCalculator::mysqlHaversineExpression(
            'issuers.latitude',
            'issuers.longitude',
            $userLatitude,
            $userLongitude
        );

        $query
            ->whereNotNull('issuers.latitude')
            ->whereNotNull('issuers.longitude')
            ->whereRaw("{$distanceExpression} <= ?", [self::RADIUS_KM]);
    }

    /**
     * Cidades/estados que têm emitentes com dados — alimenta o seletor de
     * "outra cidade" na Lista de Compras só com localidades que existem de fato.
     */
    public function availableCities(): Collection
    {
        return Issuer::query()
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->select('city', 'state')
            ->distinct()
            ->orderBy('state')
            ->orderBy('city')
            ->get();
    }

    // ─────────────────────────── Listas e itens ───────────────────────────

    /**
     * Listas do usuário com quantidade, itens comprados e total estimado (preço × quantidade; item sem preço vale 0).
     */
    public function listsWithTotals(int $userId): Collection
    {
        return ShoppingList::where('user_id', $userId)
            ->withCount([
                'items',
                'items as purchased_count' => fn ($query) => $query->whereNotNull('purchased_at'),
            ])
            ->withSum('items as items_total', DB::raw('COALESCE(unit_price, 0) * quantity'))
            ->orderByDesc('updated_at')
            ->get();
    }

    public function createList(int $userId, ?string $name): ShoppingList
    {
        return ShoppingList::create([
            'user_id' => $userId,
            'name' => $name ?: 'Lista de compras '.Carbon::now()->format(self::DEFAULT_NAME_FORMAT),
        ]);
    }

    /**
     * Adiciona o item; se o mesmo produto do mesmo mercado já está pendente na lista, soma a quantidade
     * (e assume o preço novo) em vez de criar uma segunda linha.
     *
     * @param  array{description: string, unit?: ?string, unit_price?: mixed, issuer_id?: ?int, quantity: int}  $data
     * @return array{item: ShoppingListItem, merged: bool}
     */
    public function addItem(ShoppingList $list, array $data): array
    {
        $existing = $list->items()
            ->whereNull('purchased_at')
            ->where('description', $data['description'])
            ->when(
                ($data['issuer_id'] ?? null) === null,
                fn ($query) => $query->whereNull('issuer_id'),
                fn ($query) => $query->where('issuer_id', $data['issuer_id'])
            )
            ->first();

        if ($existing !== null) {
            $existing->update(array_filter([
                'quantity' => $existing->quantity + (int) $data['quantity'],
                'unit_price' => $data['unit_price'] ?? null,
            ], fn ($value) => $value !== null));
            $item = $existing;
        } else {
            $item = $list->items()->create([
                'issuer_id' => $data['issuer_id'] ?? null,
                'description' => $data['description'],
                'unit' => $data['unit'] ?? null,
                'unit_price' => $data['unit_price'] ?? null,
                'quantity' => $data['quantity'],
            ]);
        }

        $list->touch();

        return ['item' => $item->load('issuer.nicknameForUser'), 'merged' => $existing !== null];
    }

    public function updateQuantity(ShoppingList $list, ShoppingListItem $item, int $quantity): void
    {
        $item->update(['quantity' => $quantity]);
        $list->touch();
    }

    public function removeItem(ShoppingList $list, ShoppingListItem $item): void
    {
        $item->delete();
        $list->touch();
    }

    public function togglePurchased(ShoppingList $list, ShoppingListItem $item): ShoppingListItem
    {
        $item->purchased_at = $item->purchased_at ? null : Carbon::now();
        $item->save();
        $list->touch();

        return $item;
    }

    /** Marca como comprados todos os itens pendentes. */
    public function markAllPurchased(ShoppingList $list): int
    {
        $count = $list->items()->whereNull('purchased_at')->update(['purchased_at' => Carbon::now()]);
        $list->touch();

        return $count;
    }

    /** Cópia da lista com todos os itens de volta a "a comprar". */
    public function duplicate(ShoppingList $list): ShoppingList
    {
        return DB::transaction(function () use ($list) {
            $copy = ShoppingList::create([
                'user_id' => $list->user_id,
                'name' => mb_substr($list->name, 0, 244).' (cópia)',
            ]);

            $list->items->each(fn (ShoppingListItem $item) => $copy->items()->create([
                'issuer_id' => $item->issuer_id,
                'description' => $item->description,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
            ]));

            return $copy;
        });
    }

    // ─────────────────────────── Preços ───────────────────────────

    /**
     * Repõe o preço dos itens pendentes com o da compra mais recente do mesmo produto no mesmo mercado
     * (de qualquer usuário, como na busca). O preço de cada item é o da data em que foi adicionado.
     *
     * @return array{updated: int, changes: list<array{item_id: int, old: ?float, new: float}>, total: float}
     */
    public function refreshPrices(ShoppingList $list, int $userId): array
    {
        $changes = [];

        $list->items()->whereNull('purchased_at')->whereNotNull('issuer_id')->get()
            ->each(function (ShoppingListItem $item) use ($userId, &$changes) {
                $latest = $this->latestPrice($userId, (int) $item->issuer_id, $item->description);

                if ($latest === null || abs($latest - (float) $item->unit_price) < 0.0001) {
                    return;
                }

                $changes[] = ['item_id' => $item->id, 'old' => $item->unit_price !== null ? (float) $item->unit_price : null, 'new' => $latest];
                $item->update(['unit_price' => $latest]);
            });

        if ($changes !== []) {
            $list->touch();
        }

        return [
            'updated' => count($changes),
            'changes' => $changes,
            'total' => (float) $list->items()->sum(DB::raw('COALESCE(unit_price, 0) * quantity')),
        ];
    }

    private function latestPrice(int $userId, int $issuerId, string $description): ?float
    {
        $nameSql = $this->aliasService->canonicalNameSql();

        $query = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.issuer_id', $issuerId);
        $this->aliasService->joinCanonicalNames($query, $userId);

        $price = $query
            ->where(fn ($q) => $q->whereRaw("{$nameSql} = ?", [$description])->orWhere('invoices_items.description', $description))
            ->orderByDesc('invoices.issued_at')
            ->orderByDesc('invoices_items.id')
            ->value('invoices_items.unit_price');

        return $price !== null ? (float) $price : null;
    }

    /**
     * Onde a lista sairia mais barata: para cada item pendente com preço, o menor preço atual (não "antigo") do
     * mesmo produto em OUTRO mercado da área de busca do usuário, e quanto se economiza na quantidade da lista.
     *
     * @return array{suggestions: list<array<string, mixed>>, total_saving: float}
     */
    public function savings(ShoppingList $list, User $user): array
    {
        $profile = $user->profile;
        $favoriteIds = $user->favoriteIssuers()->pluck('issuers.id');
        $suggestions = [];

        $list->items()->whereNull('purchased_at')->whereNotNull('issuer_id')->whereNotNull('unit_price')
            ->limit(self::SAVINGS_MAX_ITEMS)->get()
            ->each(function (ShoppingListItem $item) use ($user, $profile, $favoriteIds, &$suggestions) {
                $better = $this->searchProducts(
                    $user->id,
                    $item->description,
                    $favoriteIds,
                    $profile?->cidade,
                    $profile?->estado,
                    $profile?->latitude,
                    $profile?->longitude
                )->first(fn ($result) => $result->description === $item->description
                    && ! $result->is_stale
                    && (int) $result->issuer_id !== (int) $item->issuer_id
                    && (float) $result->unit_price < (float) $item->unit_price);

                if ($better === null) {
                    return;
                }

                $perUnit = (float) $item->unit_price - (float) $better->unit_price;

                $suggestions[] = [
                    'item_id' => $item->id,
                    'current_price' => (float) $item->unit_price,
                    'issuer_id' => (int) $better->issuer_id,
                    'issuer_name' => $better->issuer_name,
                    'unit_price' => (float) $better->unit_price,
                    'issued_at' => $better->issued_at,
                    'saving' => round($perUnit * $item->quantity, 2),
                ];
            });

        return [
            'suggestions' => $suggestions,
            'total_saving' => round(array_sum(array_column($suggestions, 'saving')), 2),
        ];
    }
}
