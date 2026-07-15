<?php

namespace App\Services;

use App\Models\InvoiceItem;
use Carbon\Carbon;
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
                DB::raw('MIN(invoices.issued_at) as first_purchased_at')
            )
            ->groupBy('invoices_items.description')
            ->havingRaw('COUNT(DISTINCT invoices.id) >= 3')
            ->orderByDesc('purchase_count')
            ->get()
            ->map(function ($item) {
                $spanDays = max(
                    Carbon::parse($item->first_purchased_at)->diffInDays(Carbon::parse($item->last_purchased_at)),
                    1
                );
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
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            })
            ->where('invoices.user_id', $userId)
            ->whereIn('invoices_items.description', $topDescriptions)
            ->select(
                'invoices_items.description',
                'issuers.id as issuer_id',
                DB::raw('COALESCE(issuer_nicknames.nickname, issuers.name) as issuer_name'),
                DB::raw('AVG(invoices_items.unit_price) as avg_price'),
                DB::raw('COUNT(*) as count'),
                DB::raw('MAX(invoices_items.unit) as unit')
            )
            ->groupBy('invoices_items.description', 'issuers.id', 'issuers.name', 'issuer_nicknames.nickname')
            ->get()
            ->groupBy('description')
            ->map(fn ($group) => $group->sortBy('avg_price')->first());
    }
}
