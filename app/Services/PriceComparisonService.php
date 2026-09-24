<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Support\DistanceCalculator;
use App\Support\FullTextQuery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ranking de "onde está mais barato" — reaproveita a agregação cross-user da Lista de Compras
 * (App\Services\ShoppingListService), agrupada por cidade ou por emitente. Nunca expõe qual usuário
 * originou qual preço.
 *
 * O preço que vale é o ATUAL: a compra mais recente do produto em cada mercado. Compra mais recente com
 * mais de FRESH_DAYS é "antiga" (`is_stale`) e o mercado/cidade vem depois dos atuais. `min_price`, `avg_price`
 * e `sample_count` continuam como referência histórica. Preço zero (brinde, linha de desconto) não conta.
 *
 * O fluxo é em duas etapas: searchProducts() (busca difusa, só para listar candidatos) e, escolhido UM produto,
 * os demais métodos usam o nome por igualdade — evita misturar "arroz branco" e "arroz integral". Produtos
 * vendidos em unidades diferentes (KG, UN) não se comparam: `units()` lista as unidades e `$unit` filtra.
 */
class PriceComparisonService
{
    public const FRESH_DAYS = ShoppingListService::FRESH_DAYS;

    private const LIMIT = 20;

    public function __construct(private readonly ProductAliasService $aliasService) {}

    /**
     * Produtos candidatos (nome canônico, amostras, menor preço atual) para o seletor da tela.
     */
    public function searchProducts(string $query, int $userId): Collection
    {
        $itemsQuery = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->where('invoices_items.unit_price', '>', 0);
        $this->aliasService->joinCanonicalNames($itemsQuery, $userId);
        $nameSql = $this->aliasService->canonicalNameSql();

        FullTextQuery::applyOr($itemsQuery, $query, ['invoices_items.description', 'product_aliases.canonical_name']);

        return $itemsQuery
            ->selectRaw("{$nameSql} as name")
            ->selectRaw('COUNT(*) as sample_count')
            ->selectRaw('MIN(invoices_items.unit_price) as min_price')
            ->selectRaw('MIN(CASE WHEN invoices.issued_at >= ? THEN invoices_items.unit_price END) as current_min_price', [$this->cutoff()->toDateTimeString()])
            ->selectRaw('MAX(invoices.issued_at) as last_purchased_at')
            ->groupBy(DB::raw($nameSql))
            ->orderByDesc('sample_count')
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * Unidades em que o produto foi vendido, da mais comum para a menos comum.
     *
     * @return Collection<int, object{unit: string, sample_count: int}>
     */
    public function units(string $productName, int $userId): Collection
    {
        return $this->productBase($productName, $userId, null)
            ->selectRaw("COALESCE(invoices_items.unit, '') as unit")
            ->selectRaw('COUNT(*) as sample_count')
            ->groupBy(DB::raw("COALESCE(invoices_items.unit, '')"))
            ->orderByDesc('sample_count')
            ->get()
            ->map(fn ($row) => (object) ['unit' => (string) $row->unit, 'sample_count' => (int) $row->sample_count]);
    }

    /**
     * Ranking de cidades para o produto: menor preço ATUAL entre os mercados da cidade (atuais primeiro).
     *
     * @return Collection<int, object>
     */
    public function byCity(string $productName, int $userId, ?string $unit = null, ?string $userCity = null, ?string $userState = null): Collection
    {
        $latest = $this->latestPerIssuer($productName, $userId, $unit)
            ->filter(fn ($row) => filled($row->city) && filled($row->state));

        $history = $this->productBase($productName, $userId, $unit)
            ->whereNotNull('issuers.city')->where('issuers.city', '!=', '')
            ->whereNotNull('issuers.state')->where('issuers.state', '!=', '')
            ->select('issuers.city', 'issuers.state')
            ->selectRaw('MIN(invoices_items.unit_price) as min_price')
            ->selectRaw('AVG(invoices_items.unit_price) as avg_price')
            ->selectRaw('COUNT(*) as sample_count')
            ->groupBy('issuers.city', 'issuers.state')
            ->get()
            ->keyBy(fn ($row) => $row->city.'|'.$row->state);

        return $latest
            ->groupBy(fn ($row) => $row->city.'|'.$row->state)
            ->map(function (Collection $issuers, string $key) use ($history, $userCity, $userState) {
                // Preço atual mais baixo da cidade; sem nenhum atual, o mais baixo dos antigos.
                $best = $issuers->sortBy([['is_stale', 'asc'], ['price', 'asc']])->first();
                $stats = $history[$key];

                return (object) [
                    'city' => $best->city,
                    'state' => $best->state,
                    'price' => $best->price,
                    'issued_at' => $best->issued_at,
                    'is_stale' => $best->is_stale,
                    'cheapest_issuer_name' => $best->issuer_name,
                    'issuer_count' => $issuers->count(),
                    'min_price' => (float) $stats->min_price,
                    'avg_price' => (float) $stats->avg_price,
                    'sample_count' => (int) $stats->sample_count,
                    'is_user_city' => $userCity !== null && mb_strtolower($best->city) === mb_strtolower($userCity)
                        && mb_strtolower((string) $best->state) === mb_strtolower((string) $userState),
                ];
            })
            ->sortBy([['is_stale', 'asc'], ['price', 'asc']])
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * Ranking de mercados dentro de uma cidade: preço ATUAL (compra mais recente) de cada um, atuais primeiro do
     * menor ao maior. Com as coordenadas do usuário (MySQL), traz também `distance_km`.
     *
     * @return Collection<int, object>
     */
    public function byIssuer(string $productName, string $city, string $state, int $userId, ?string $unit = null, ?float $latitude = null, ?float $longitude = null): Collection
    {
        $latest = $this->latestPerIssuer($productName, $userId, $unit, $city, $state, $latitude, $longitude);

        $history = $this->productBase($productName, $userId, $unit)
            ->where('issuers.city', $city)->where('issuers.state', $state)
            ->select('issuers.id as issuer_id')
            ->selectRaw('MIN(invoices_items.unit_price) as min_price')
            ->selectRaw('AVG(invoices_items.unit_price) as avg_price')
            ->selectRaw('COUNT(*) as sample_count')
            ->groupBy('issuers.id')
            ->get()
            ->keyBy('issuer_id');

        return $latest
            ->map(fn ($row) => (object) [
                'issuer_id' => (int) $row->issuer_id,
                'issuer_name' => $row->issuer_name,
                'price' => $row->price,
                'unit' => $row->unit,
                'issued_at' => $row->issued_at,
                'is_stale' => $row->is_stale,
                'distance_km' => isset($row->distance_km) ? round((float) $row->distance_km, 1) : null,
                'min_price' => (float) $history[$row->issuer_id]->min_price,
                'avg_price' => (float) $history[$row->issuer_id]->avg_price,
                'sample_count' => (int) $history[$row->issuer_id]->sample_count,
            ])
            ->sortBy([['is_stale', 'asc'], ['price', 'asc']])
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * Menor oferta ATUAL (compra mais recente de cada mercado, dentro de FRESH_DAYS) do produto — usada pelo alerta
     * de queda de preço dos favoritos. Sem nenhuma compra recente, não há oferta (null): o alerta não dispara com
     * preço velho.
     *
     * @return array{price: float, issuer_name: string, city: string, state: string}|null
     */
    public function cheapestOffer(string $productName, int $userId): ?array
    {
        $best = $this->latestPerIssuer($productName, $userId, null)
            ->reject(fn ($row) => $row->is_stale)
            ->sortBy('price')
            ->first();

        if ($best === null) {
            return null;
        }

        return [
            'price' => $best->price,
            'issuer_name' => $best->issuer_name,
            'city' => (string) $best->city,
            'state' => (string) $best->state,
        ];
    }

    private function cutoff(): Carbon
    {
        return Carbon::now()->subDays(self::FRESH_DAYS);
    }

    /**
     * Itens do produto (nome exato para este usuário) de qualquer usuário, com preço válido. Igualdade sobre a
     * descrição original (indexada); ver ProductAliasService::descriptionsFor().
     */
    private function productBase(string $productName, int $userId, ?string $unit): Builder
    {
        return InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->whereIn('invoices_items.description', $this->aliasService->descriptionsFor($productName, $userId))
            ->where('invoices_items.unit_price', '>', 0)
            ->when(filled($unit), fn ($query) => $query->where('invoices_items.unit', $unit));
    }

    /**
     * A compra mais recente do produto em cada mercado (opcionalmente numa cidade), com `is_stale`.
     *
     * @return Collection<int, InvoiceItem>
     */
    private function latestPerIssuer(string $productName, int $userId, ?string $unit, ?string $city = null, ?string $state = null, ?float $latitude = null, ?float $longitude = null): Collection
    {
        $inner = $this->productBase($productName, $userId, $unit)
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            })
            ->when($city !== null && $state !== null, fn ($query) => $query->where('issuers.city', $city)->where('issuers.state', $state))
            ->select('issuers.id as issuer_id', 'issuers.city', 'issuers.state', 'invoices_items.unit', 'invoices.issued_at')
            ->selectRaw('invoices_items.unit_price as price')
            ->selectRaw('COALESCE(issuer_nicknames.nickname, issuers.name) as issuer_name')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY issuers.id ORDER BY invoices.issued_at DESC, invoices_items.id DESC) as rn');

        if ($latitude !== null && $longitude !== null && DistanceCalculator::isMySql()) {
            $inner->selectRaw(DistanceCalculator::mysqlHaversineExpression('issuers.latitude', 'issuers.longitude', $latitude, $longitude).' as distance_km');
        }

        $cutoff = $this->cutoff();

        return InvoiceItem::query()->fromSub($inner, 'latest')->where('rn', 1)->get()
            ->each(function ($row) use ($cutoff) {
                $row->price = (float) $row->price;
                $row->is_stale = Carbon::parse($row->issued_at)->lt($cutoff);
            });
    }
}
