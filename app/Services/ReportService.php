<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function buildReportData(int $userId, array $filters): array
    {
        $startDate  = $filters['start_date']  ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate    = $filters['end_date']    ?? Carbon::now()->format('Y-m-d');
        $issuerId   = $filters['issuer_id']   ?? null;
        $categoryId = $filters['category_id'] ?? null;

        $query = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('categories', 'categories.id', '=', 'invoices_items.category_id')
            ->where('invoices.user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereDate('invoices.issued_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('invoices.issued_at', '<=', $endDate))
            ->when($issuerId, fn ($q) => $q->where('invoices.issuer_id', $issuerId))
            ->when($categoryId, fn ($q) => $q->where('invoices_items.category_id', $categoryId));

        $items = (clone $query)->select(
            'invoices_items.description',
            'invoices_items.quantity',
            'invoices_items.unit',
            'invoices_items.unit_price',
            'invoices_items.total_price',
            'invoices.issued_at',
            'issuers.name as issuer_name',
            DB::raw("COALESCE(categories.name, 'Sem categoria') as category_name"),
            DB::raw("COALESCE(categories.color, '#94A3B8') as category_color")
        )
            ->orderByDesc('invoices.issued_at')
            ->get();

        $summary = (clone $query)->select(
            DB::raw('SUM(invoices_items.total_price) as total_amount'),
            DB::raw('COUNT(invoices_items.id) as total_items'),
            DB::raw('COUNT(DISTINCT invoices.id) as total_invoices')
        )->first();

        $categoryBreakdown = (clone $query)->select(
            DB::raw("COALESCE(categories.name, 'Sem categoria') as category_name"),
            DB::raw("COALESCE(categories.color, '#94A3B8') as category_color"),
            DB::raw('SUM(invoices_items.total_price) as total')
        )
            ->groupBy('category_name', 'category_color')
            ->orderByDesc('total')
            ->get();

        return [
            'items'             => $items,
            'summary'           => $summary,
            'categoryBreakdown' => $categoryBreakdown,
            'filters'           => compact('startDate', 'endDate', 'issuerId', 'categoryId'),
            'issuers'           => Issuer::whereHas('invoices', fn ($q) => $q->where('user_id', $userId))->orderBy('name')->get(),
            'categories'        => Category::forUser($userId)->orderBy('name')->get(),
        ];
    }
}
