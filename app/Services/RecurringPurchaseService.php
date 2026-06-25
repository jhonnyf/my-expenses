<?php

namespace App\Services;

use App\Models\InvoiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecurringPurchaseService
{
    public function getRecurringItems(int $userId): Collection
    {
        return InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->select(
                'invoices_items.description',
                DB::raw('COUNT(DISTINCT invoices.id) as purchase_count'),
                DB::raw('COUNT(DISTINCT invoices.issuer_id) as issuer_count'),
                DB::raw('AVG(invoices_items.unit_price) as avg_price'),
                DB::raw('MIN(invoices_items.unit_price) as min_price'),
                DB::raw('MAX(invoices_items.unit_price) as max_price'),
                DB::raw('MAX(invoices.issued_at) as last_purchased_at'),
                DB::raw('MIN(invoices.issued_at) as first_purchased_at'),
                DB::raw('DATEDIFF(MAX(invoices.issued_at), MIN(invoices.issued_at)) as date_span_days')
            )
            ->groupBy('invoices_items.description')
            ->havingRaw('COUNT(DISTINCT invoices.id) >= 3')
            ->orderByDesc('purchase_count')
            ->get()
            ->map(function ($item) {
                $spanDays = max($item->date_span_days, 1);
                $item->avg_interval_days = round($spanDays / max($item->purchase_count - 1, 1));
                $item->purchases_per_month = round(($item->purchase_count / $spanDays) * 30, 1);

                return $item;
            })
            ->sortByDesc('purchases_per_month')
            ->values();
    }

    public function getBestIssuers(int $userId, Collection $topDescriptions): Collection
    {
        if ($topDescriptions->isEmpty()) {
            return collect();
        }

        return InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->where('invoices.user_id', $userId)
            ->whereIn('invoices_items.description', $topDescriptions)
            ->select(
                'invoices_items.description',
                'issuers.id as issuer_id',
                'issuers.name as issuer_name',
                DB::raw('AVG(invoices_items.unit_price) as avg_price'),
                DB::raw('COUNT(*) as count'),
                DB::raw('MAX(invoices_items.unit) as unit')
            )
            ->groupBy('invoices_items.description', 'issuers.id', 'issuers.name')
            ->get()
            ->groupBy('description')
            ->map(fn ($group) => $group->sortBy('avg_price')->first());
    }
}
