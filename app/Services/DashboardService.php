<?php

namespace App\Services;

use App\DTOs\OverallStats;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getViewData(int $userId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();

        $stats = $this->getOverallStats($userId);
        $monthComparison = $this->getMonthComparison($userId, $now, $startOfMonth);
        $currentMonthExpenses = $monthComparison['currentMonthExpenses'];

        return [
            'totalExpenses'       => $stats->totalExpenses,
            'totalTaxes'          => $stats->totalTaxes,
            'totalPurchases'      => $stats->totalPurchases,
            'averageTicket'       => $this->calculateAverageTicket($stats),
            ...$monthComparison,
            'lastPurchase'        => $this->getLastPurchase($userId),
            'paymentDistribution' => $this->getPaymentDistribution($userId),
            'budgets'             => $this->getBudgets($userId, $startOfMonth, $currentMonthExpenses),
            'monthlyExpenses'     => $this->getMonthlyExpenses($userId, $now),
            'spendingByCategory'  => $this->getSpendingByCategory($userId),
            'topIssuers'          => $this->getTopIssuers($userId),
            'topProducts'         => $this->getTopProducts($userId),
            'paymentLabels'       => $this->paymentLabels(),
            'paymentIcons'        => $this->paymentIcons(),
        ];
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
        $endOfLastMonth   = $now->copy()->subMonth()->endOfMonth();

        [$currentMonthExpenses, $lastMonthExpenses] = Cache::remember(
            "dashboard.month_comparison.{$userId}",
            300,
            function () use ($userId, $startOfMonth, $startOfLastMonth, $endOfLastMonth) {
                $result = Invoice::where('user_id', $userId)
                    ->where('issued_at', '>=', $startOfLastMonth)
                    ->selectRaw('
                        COALESCE(SUM(CASE WHEN issued_at >= ? THEN total_amount ELSE 0 END), 0) as current_month,
                        COALESCE(SUM(CASE WHEN issued_at <= ? THEN total_amount ELSE 0 END), 0) as last_month
                    ', [$startOfMonth, $endOfLastMonth])
                    ->first();

                return [(float) $result->current_month, (float) $result->last_month];
            }
        );

        $monthVariation = $lastMonthExpenses > 0
            ? (($currentMonthExpenses - $lastMonthExpenses) / $lastMonthExpenses) * 100
            : null;

        return [
            'currentMonthExpenses' => $currentMonthExpenses,
            'lastMonthExpenses'    => $lastMonthExpenses,
            'monthVariation'       => $monthVariation,
        ];
    }

    private function getLastPurchase(int $userId): ?Invoice
    {
        return Invoice::where('user_id', $userId)
            ->select(['id', 'issuer_id', 'issued_at', 'total_amount'])
            ->with('issuer')
            ->orderByDesc('issued_at')
            ->first();
    }

    private function getPaymentDistribution(int $userId): Collection
    {
        return Cache::remember("dashboard.payment_distribution.{$userId}", 300, function () use ($userId) {
            return InvoicePayment::join('invoices', 'invoices.id', '=', 'invoices_payments.invoice_id')
                ->where('invoices.user_id', $userId)
                ->select('invoices_payments.method', DB::raw('SUM(invoices_payments.amount) as total'))
                ->groupBy('invoices_payments.method')
                ->orderByDesc('total')
                ->get();
        });
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
            $budget->remaining  = (float) $budget->amount - $budget->spent;

            return $budget;
        });
    }

    private function getMonthlyExpenses(int $userId, Carbon $now): Collection
    {
        return Cache::remember("dashboard.monthly_expenses.{$userId}", 300, function () use ($userId, $now) {
            return Invoice::where('user_id', $userId)
                ->where('issued_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
                ->select(DB::raw("DATE_FORMAT(issued_at, '%Y-%m') as month"), DB::raw('SUM(total_amount) as total'))
                ->groupBy('month')
                ->orderBy('month')
                ->get();
        });
    }

    private function getSpendingByCategory(int $userId): Collection
    {
        return Cache::remember("dashboard.spending_by_category.{$userId}", 300, function () use ($userId) {
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
        });
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

    private function paymentLabels(): array
    {
        return [
            'dinheiro'         => 'Dinheiro',
            'cheque'           => 'Cheque',
            'cartao_credito'   => 'Cartão de Crédito',
            'cartao_debito'    => 'Cartão de Débito',
            'credito_loja'     => 'Crédito Loja',
            'vale_alimentacao' => 'Vale Alimentação',
            'vale_refeicao'    => 'Vale Refeição',
            'vale_presente'    => 'Vale Presente',
            'vale_combustivel' => 'Vale Combustível',
            'boleto'           => 'Boleto',
            'sem_pagamento'    => 'Sem Pagamento',
            'outros'           => 'Outros',
            'pix'              => 'Pix',
        ];
    }

    private function paymentIcons(): array
    {
        return [
            'dinheiro'         => 'ki-filled ki-dollar',
            'cartao_credito'   => 'ki-filled ki-credit-cart',
            'cartao_debito'    => 'ki-filled ki-credit-cart',
            'vale_alimentacao' => 'ki-filled ki-basket',
            'vale_refeicao'    => 'ki-filled ki-coffee',
            'pix'              => 'ki-filled ki-send',
            'boleto'           => 'ki-filled ki-document',
        ];
    }
}
