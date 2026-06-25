<?php

namespace App\Http\Controllers;

use App\DTOs\OverallStats;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();

        $stats = $this->getOverallStats($userId);
        $monthComparison = $this->getMonthComparison($userId, $now, $startOfMonth);
        $currentMonthExpenses = $monthComparison['currentMonthExpenses'];

        return view('dashboard.index', [
            'totalExpenses' => $stats->totalExpenses,
            'totalTaxes' => $stats->totalTaxes,
            'totalPurchases' => $stats->totalPurchases,
            'averageTicket' => $this->calculateAverageTicket($stats),
            ...$monthComparison,
            'lastPurchase' => $this->getLastPurchase($userId),
            'paymentDistribution' => $this->getPaymentDistribution($userId),
            'budgets' => $this->getBudgets($userId, $startOfMonth, $currentMonthExpenses),
            'monthlyExpenses' => $this->getMonthlyExpenses($userId, $now),
            'spendingByCategory' => $this->getSpendingByCategory($userId),
            'topIssuers' => $this->getTopIssuers($userId),
            'topProducts' => $this->getTopProducts($userId),
        ]);
    }

    private function calculateAverageTicket(OverallStats $stats): float
    {
        return $stats->totalPurchases > 0
            ? $stats->totalExpenses / $stats->totalPurchases
            : 0.0;
    }

    private function getOverallStats(int $userId): OverallStats
    {
        return Cache::remember("dashboard.overall_stats.{$userId}", 300, function () use ($userId) {
            $result = Invoice::where('user_id', $userId)
                ->selectRaw('
                    COALESCE(SUM(total_amount), 0) as totalExpenses,
                    COALESCE(SUM(total_taxes), 0) as totalTaxes,
                    COUNT(id) as totalPurchases
                ')
                ->first();

            return OverallStats::fromQueryResult($result);
        });
    }

    private function getMonthComparison(int $userId, Carbon $now, Carbon $startOfMonth): array
    {
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $currentMonthExpenses = Invoice::where('user_id', $userId)
            ->where('issued_at', '>=', $startOfMonth)
            ->sum('total_amount');

        $lastMonthExpenses = Invoice::where('user_id', $userId)
            ->whereBetween('issued_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('total_amount');

        $monthVariation = $lastMonthExpenses > 0
            ? (($currentMonthExpenses - $lastMonthExpenses) / $lastMonthExpenses) * 100
            : null;

        return [
            'currentMonthExpenses' => $currentMonthExpenses,
            'lastMonthExpenses' => $lastMonthExpenses,
            'monthVariation' => $monthVariation,
        ];
    }

    private function getLastPurchase(int $userId): ?Invoice
    {
        return Invoice::where('user_id', $userId)
            ->with('issuer')
            ->orderByDesc('issued_at')
            ->first();
    }

    private function getPaymentDistribution(int $userId): Collection
    {
        return InvoicePayment::join('invoices', 'invoices.id', '=', 'invoices_payments.invoice_id')
            ->where('invoices.user_id', $userId)
            ->select('invoices_payments.method', DB::raw('SUM(invoices_payments.amount) as total'))
            ->groupBy('invoices_payments.method')
            ->orderByDesc('total')
            ->get();
    }

    private function getBudgets(int $userId, Carbon $startOfMonth, float $currentMonthExpenses): Collection
    {
        $budgets = Budget::where('user_id', $userId)->with('category')->get();

        $monthlySpending = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->where('invoices.issued_at', '>=', $startOfMonth)
            ->select('invoices_items.category_id', DB::raw('SUM(invoices_items.total_price) as total'))
            ->groupBy('invoices_items.category_id')
            ->pluck('total', 'category_id');

        return $budgets->map(function (Budget $budget) use ($monthlySpending, $currentMonthExpenses) {
            $budget->spent = $budget->category_id
                ? (float) ($monthlySpending[$budget->category_id] ?? 0)
                : (float) $currentMonthExpenses;
            $budget->percentage = $budget->amount > 0 ? ($budget->spent / $budget->amount) * 100 : 0.0;

            return $budget;
        });
    }

    private function getMonthlyExpenses(int $userId, Carbon $now): Collection
    {
        return Invoice::where('user_id', $userId)
            ->where('issued_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(issued_at, '%Y-%m') as month"), DB::raw('SUM(total_amount) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    private function getSpendingByCategory(int $userId): Collection
    {
        return InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->leftJoin('categories', 'categories.id', '=', 'invoices_items.category_id')
            ->select(
                DB::raw("COALESCE(categories.name, 'Sem categoria') as category_name"),
                DB::raw("COALESCE(categories.color, '#94A3B8') as category_color"),
                DB::raw('SUM(invoices_items.total_price) as total')
            )
            ->groupBy('category_name', 'category_color')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }

    private function getTopIssuers(int $userId): Collection
    {
        return Cache::remember("dashboard.top_issuers.{$userId}", 300, function () use ($userId) {
            return Invoice::where('invoices.user_id', $userId)
                ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
                ->select('issuers.name', DB::raw('SUM(invoices.total_amount) as total'), DB::raw('COUNT(invoices.id) as count'))
                ->groupBy('issuers.id', 'issuers.name')
                ->orderByDesc('total')
                ->limit(5)
                ->get();
        });
    }

    private function getTopProducts(int $userId): Collection
    {
        return Cache::remember("dashboard.top_products.{$userId}", 300, function () use ($userId) {
            return InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
                ->where('invoices.user_id', $userId)
                ->select('invoices_items.description', DB::raw('COUNT(*) as frequency'), DB::raw('AVG(invoices_items.unit_price) as avg_price'))
                ->groupBy('invoices_items.description')
                ->orderByDesc('frequency')
                ->limit(10)
                ->get();
        });
    }
}
