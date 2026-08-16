<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Support\DistanceCalculator;
use App\Support\FullTextQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShoppingListService
{
    private const RADIUS_KM = 15;

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
        $nameSql = $this->aliasService->canonicalNameSql();

        FullTextQuery::applyOr(
            $itemsQuery,
            $query,
            ['invoices_items.description', 'product_aliases.canonical_name'],
            existsCallbacks: [$this->aliasService->otherUsersAliasFullTextExists($query, $userId)]
        );

        $itemsQuery
            ->select(
                DB::raw("{$nameSql} as description"),
                'invoices_items.unit_price',
                'invoices_items.unit',
                'invoices_items.code',
                'issuers.id as issuer_id',
                'invoices.issued_at'
            )
            ->selectRaw('COALESCE(issuer_nicknames.nickname, issuers.name) as issuer_name')
            ->selectRaw('IF(issuers.id IN ('.($favoriteIssuerIds->isNotEmpty() ? $favoriteIssuerIds->implode(',') : '0').'), 1, 0) as is_favorite')
            ->selectRaw('IF(invoices.user_id = ?, 1, 0) as is_own', [$userId]);

        $this->applyLocationFilter($itemsQuery, $filterCity, $filterState, $userLatitude, $userLongitude);

        return $itemsQuery
            ->orderByDesc('is_favorite')
            ->orderBy('invoices_items.unit_price', 'asc')
            ->limit(20)
            ->get();
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
}
