<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $stats = Invoice::where('user_id', $userId)
            ->selectRaw('
                COALESCE(SUM(total_amount), 0) as totalExpenses,
                COALESCE(SUM(total_taxes), 0) as totalTaxes,
                COUNT(id) as totalPurchases
            ')->first();

        $currentMonthExpenses = Invoice::where('user_id', $userId)
            ->where('issued_at', '>=', $startOfMonth)
            ->sum('total_amount');

        $lastMonthExpenses = Invoice::where('user_id', $userId)
            ->whereBetween('issued_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('total_amount');

        $monthVariation = $lastMonthExpenses > 0
            ? (($currentMonthExpenses - $lastMonthExpenses) / $lastMonthExpenses) * 100
            : null;

        $topIssuers = Invoice::where('invoices.user_id', $userId)
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->select('issuers.name', DB::raw('SUM(invoices.total_amount) as total'), DB::raw('COUNT(invoices.id) as count'))
            ->groupBy('issuers.id', 'issuers.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $monthlyExpenses = Invoice::where('user_id', $userId)
            ->where('issued_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(issued_at, '%Y-%m') as month"), DB::raw('SUM(total_amount) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $lastPurchase = Invoice::where('user_id', $userId)
            ->with('issuer')
            ->orderByDesc('issued_at')
            ->first();

        $paymentDistribution = InvoicePayment::join('invoices', 'invoices.id', '=', 'invoices_payments.invoice_id')
            ->where('invoices.user_id', $userId)
            ->select('invoices_payments.method', DB::raw('SUM(invoices_payments.amount) as total'))
            ->groupBy('invoices_payments.method')
            ->orderByDesc('total')
            ->get();

        $averageTicket = $stats->totalPurchases > 0
            ? $stats->totalExpenses / $stats->totalPurchases
            : 0;

        $topProducts = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->select('invoices_items.description', DB::raw('COUNT(*) as frequency'), DB::raw('AVG(invoices_items.unit_price) as avg_price'))
            ->groupBy('invoices_items.description')
            ->orderByDesc('frequency')
            ->limit(10)
            ->get();

        $spendingByCategory = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->leftJoin('categories', 'categories.id', '=', 'invoices_items.category_id')
            ->select(
                DB::raw("COALESCE(categories.name, 'Sem categoria') as category_name"),
                DB::raw("COALESCE(categories.color, '#94A3B8') as category_color"),
                DB::raw('SUM(invoices_items.total_price) as total')
            )
            ->groupBy('category_name', 'category_color')
            ->orderByDesc('total')
            ->get();

        return view('dashboard.index', [
            'totalExpenses' => $stats->totalExpenses,
            'totalTaxes' => $stats->totalTaxes,
            'totalPurchases' => $stats->totalPurchases,
            'currentMonthExpenses' => $currentMonthExpenses,
            'lastMonthExpenses' => $lastMonthExpenses,
            'monthVariation' => $monthVariation,
            'topIssuers' => $topIssuers,
            'monthlyExpenses' => $monthlyExpenses,
            'lastPurchase' => $lastPurchase,
            'paymentDistribution' => $paymentDistribution,
            'averageTicket' => $averageTicket,
            'topProducts' => $topProducts,
            'spendingByCategory' => $spendingByCategory,
        ]);
    }
}
