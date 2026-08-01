<?php

namespace App\Services;

use App\Models\InvoiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShoppingListService
{
    public function __construct(private readonly ProductAliasService $aliasService) {}

    /**
     * Busca produtos para montar a lista de compras entre as notas fiscais
     * de todos os usuários (não só do usuário logado), para aproveitar
     * preços e produtos já cadastrados por outras pessoas. O nome canônico
     * exibido e o apelido de loja usados continuam sendo os do usuário que
     * busca, não os de quem originou a nota.
     */
    public function searchProducts(int $userId, string $query, Collection $favoriteIssuerIds): Collection
    {
        $itemsQuery = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            });
        $this->aliasService->joinCanonicalNames($itemsQuery, $userId);
        $nameSql = $this->aliasService->canonicalNameSql();

        return $itemsQuery
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
            ->selectRaw('IF(invoices.user_id = ?, 1, 0) as is_own', [$userId])
            ->where(fn ($q) => $q
                ->where('invoices_items.description', 'like', "%{$query}%")
                ->orWhere('product_aliases.canonical_name', 'like', "%{$query}%"))
            ->orderByDesc('is_favorite')
            ->orderBy('invoices_items.unit_price', 'asc')
            ->limit(20)
            ->get();
    }
}
