<?php

namespace App\Search\Strategies;

use App\Contracts\SearchStrategyInterface;
use App\Models\InvoiceItem;
use App\Services\ProductAliasService;
use App\Support\FullTextQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductSearchStrategy implements SearchStrategyInterface
{
    public function __construct(private readonly ProductAliasService $aliasService) {}

    public function search(string $query, int $userId, ?string $city = null, ?string $state = null): Collection
    {
        $productQuery = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId);
        $this->aliasService->joinCanonicalNames($productQuery, $userId);

        $nameSql = $this->aliasService->canonicalNameSql();

        FullTextQuery::applyOr($productQuery, $query, ['invoices_items.description', 'product_aliases.canonical_name']);

        if ($city !== null && $state !== null) {
            $productQuery
                ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
                ->where('issuers.city', $city)
                ->where('issuers.state', $state);
        }

        return $productQuery
            ->select(
                DB::raw("{$nameSql} as description"),
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(invoices_items.unit_price) as avg_price')
            )
            ->groupBy(DB::raw($nameSql))
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'type' => 'product',
                'title' => $p->description,
                'subtitle' => $p->count.'x comprado - Média R$ '.number_format($p->avg_price, 2, ',', '.'),
                'url' => route('price-history.index').'?q='.urlencode($p->description),
            ]);
    }
}
