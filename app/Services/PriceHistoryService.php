<?php

namespace App\Services;

use App\Models\InvoiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PriceHistoryService
{
    public function search(string $query, int $userId): Collection
    {
        return InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->where('invoices_items.description', 'like', "%{$query}%")
            ->select(
                'invoices_items.description',
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw('MIN(invoices_items.unit_price) as min_price'),
                DB::raw('MAX(invoices_items.unit_price) as max_price'),
                DB::raw('AVG(invoices_items.unit_price) as avg_price'),
                DB::raw('MAX(invoices.issued_at) as last_purchased_at')
            )
            ->groupBy('invoices_items.description')
            ->orderByDesc('purchase_count')
            ->limit(20)
            ->get();
    }

    public function getTimeline(string $description, int $userId): array
    {
        $timeline = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            })
            ->where('invoices.user_id', $userId)
            ->where('invoices_items.description', $description)
            ->select(
                'invoices_items.unit_price',
                'invoices_items.quantity',
                'invoices_items.unit',
                'invoices.issued_at',
                'issuers.id as issuer_id'
            )
            ->selectRaw('COALESCE(issuer_nicknames.nickname, issuers.name) as issuer_name')
            ->orderBy('invoices.issued_at')
            ->limit(100)
            ->get();

        $prices = $timeline->pluck('unit_price')->map(fn ($p) => (float) $p);
        $minPrice = $prices->min() ?? 0;
        $maxPrice = $prices->max() ?? 0;
        $avgPrice = $prices->avg() ?? 0;
        $variationPct = $minPrice > 0 ? (($maxPrice - $minPrice) / $minPrice) * 100 : 0;

        return [
            'timeline' => $timeline,
            'summary' => [
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'avg_price' => round($avgPrice, 2),
                'variation_pct' => round($variationPct, 1),
            ],
        ];
    }
}
