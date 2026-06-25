<?php

namespace App\Search\Strategies;

use App\Contracts\SearchStrategyInterface;
use App\Models\InvoiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductSearchStrategy implements SearchStrategyInterface
{
    public function search(string $query, int $userId): Collection
    {
        return InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->where('invoices_items.description', 'like', "%{$query}%")
            ->select(
                'invoices_items.description',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(invoices_items.unit_price) as avg_price')
            )
            ->groupBy('invoices_items.description')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'type'     => 'product',
                'title'    => $p->description,
                'subtitle' => $p->count.'x comprado - Média R$ '.number_format($p->avg_price, 2, ',', '.'),
                'url'      => route('price-history.index').'?q='.urlencode($p->description),
            ]);
    }
}
