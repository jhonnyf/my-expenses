<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Support\FullTextQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ranking de "onde está mais barato" — reaproveita a mesma agregação cross-user
 * já usada na Lista de Compras (App\Services\ShoppingListService), mas agrupada
 * por cidade ou por emitente em vez de por item individual. Sempre retorna
 * MIN/AVG agregados, nunca expõe qual usuário originou qual preço.
 *
 * O fluxo é sempre em duas etapas: primeiro searchProducts() (busca difusa, só
 * pra listar candidatos), depois o cliente escolhe UM produto específico e os
 * demais métodos (byCity/byIssuer/cheapestOffer) usam esse nome por igualdade
 * exata — evita misturar produtos parecidos (ex: "arroz branco" e "arroz
 * integral") na mesma agregação.
 */
class PriceComparisonService
{
    public function __construct(private readonly ProductAliasService $aliasService) {}

    /**
     * Lista de produtos candidatos (nome canônico + quantas amostras existem),
     * cross-user, pra alimentar o seletor de produto na tela.
     */
    public function searchProducts(string $query, int $userId): Collection
    {
        $itemsQuery = $this->joinedQuery($userId);
        $nameSql = $this->aliasService->canonicalNameSql();

        FullTextQuery::applyOr($itemsQuery, $query, ['invoices_items.description', 'product_aliases.canonical_name']);

        return $itemsQuery
            ->selectRaw("{$nameSql} as name")
            ->selectRaw('COUNT(*) as sample_count')
            ->selectRaw('MIN(invoices_items.unit_price) as min_price')
            ->groupBy(DB::raw($nameSql))
            ->orderByDesc('sample_count')
            ->limit(20)
            ->get();
    }

    /**
     * Ranking de cidades mais baratas para o produto (nome exato) escolhido.
     */
    public function byCity(string $productName, int $userId): Collection
    {
        $itemsQuery = $this->exactMatchQuery($productName, $userId)
            ->whereNotNull('issuers.city')
            ->where('issuers.city', '!=', '')
            ->whereNotNull('issuers.state')
            ->where('issuers.state', '!=', '');

        return $itemsQuery
            ->select('issuers.city', 'issuers.state')
            ->selectRaw('MIN(invoices_items.unit_price) as min_price')
            ->selectRaw('AVG(invoices_items.unit_price) as avg_price')
            ->selectRaw('COUNT(*) as sample_count')
            ->groupBy('issuers.city', 'issuers.state')
            ->orderBy('min_price')
            ->limit(20)
            ->get();
    }

    /**
     * Ranking de emitentes (mercados) mais baratos dentro de uma cidade/estado,
     * para o produto (nome exato) escolhido.
     */
    public function byIssuer(string $productName, string $city, string $state, int $userId): Collection
    {
        $itemsQuery = $this->exactMatchQuery($productName, $userId)
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            })
            ->where('issuers.city', $city)
            ->where('issuers.state', $state);

        return $itemsQuery
            ->select('issuers.id as issuer_id')
            ->selectRaw('COALESCE(issuer_nicknames.nickname, issuers.name) as issuer_name')
            ->selectRaw('MIN(invoices_items.unit_price) as min_price')
            ->selectRaw('AVG(invoices_items.unit_price) as avg_price')
            ->selectRaw('COUNT(*) as sample_count')
            ->groupBy('issuers.id', 'issuer_nicknames.nickname', 'issuers.name')
            ->orderBy('min_price')
            ->limit(20)
            ->get();
    }

    /**
     * Menor oferta vigente pra um produto (nome exato) — preço + emitente/cidade,
     * considerando qualquer emitente (não restrito a issuers com city/state
     * preenchido) — usado pelo alerta de queda de preço de produtos favoritos.
     *
     * @return array{price: float, issuer_name: string, city: string, state: string}|null
     */
    public function cheapestOffer(string $productName, int $userId): ?array
    {
        $row = $this->exactMatchQuery($productName, $userId)
            ->select('invoices_items.unit_price', 'issuers.name as issuer_name', 'issuers.city', 'issuers.state')
            ->orderBy('invoices_items.unit_price')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'price' => (float) $row->unit_price,
            'issuer_name' => $row->issuer_name,
            'city' => (string) $row->city,
            'state' => (string) $row->state,
        ];
    }

    private function joinedQuery(int $userId)
    {
        $itemsQuery = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id');
        $this->aliasService->joinCanonicalNames($itemsQuery, $userId);

        return $itemsQuery;
    }

    private function exactMatchQuery(string $productName, int $userId)
    {
        $nameSql = $this->aliasService->canonicalNameSql();

        return $this->joinedQuery($userId)->whereRaw("{$nameSql} = ?", [$productName]);
    }
}
