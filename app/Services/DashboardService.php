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
    public function __construct(private readonly ProductAliasService $aliasService) {}

    public function getViewData(int $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ?: Carbon::now()->startOfMonth()->format('Y-m-d');
        $end = $endDate ?: Carbon::now()->format('Y-m-d');

        [$previousStart, $previousEnd] = $this->previousPeriod($start, $end);

        $stats = $this->getOverallStats($userId, $start, $end);
        $periodComparison = $this->getPeriodComparison($userId, $start, $end, $previousStart, $previousEnd);

        return [
            'filters' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'totalExpenses' => $stats->totalExpenses,
            'totalTaxes' => $stats->totalTaxes,
            'totalPurchases' => $stats->totalPurchases,
            'averageTicket' => $this->calculateAverageTicket($stats),
            ...$periodComparison,
            'previousPeriodStart' => $previousStart,
            'previousPeriodEnd' => $previousEnd,
            'lastPurchase' => $this->getLastPurchase($userId, $start, $end),
            'paymentDistribution' => $this->getPaymentDistribution($userId, $start, $end),
            'budgets' => $this->getBudgets($userId, $start, $end, $periodComparison['periodExpenses']),
            'monthlyExpenses' => $this->getMonthlyExpenses($userId, Carbon::now()),
            'spendingByCategory' => $this->getSpendingByCategory($userId, $start, $end),
            'topIssuers' => $this->getTopIssuers($userId, $start, $end),
            'topProducts' => $this->getTopProducts($userId, $start, $end),
            'paymentLabels' => $this->paymentLabels(),
            'paymentIcons' => $this->paymentIcons(),
        ];
    }

    /**
     * Período imediatamente anterior, com a mesma duração do período selecionado —
     * permite comparar qualquer intervalo (não só meses fechados) de forma justa.
     */
    private function previousPeriod(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $periodDays = $start->diffInDays(Carbon::parse($endDate)) + 1;

        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($periodDays - 1);

        return [$previousStart->format('Y-m-d'), $previousEnd->format('Y-m-d')];
    }

    private function calculateAverageTicket(OverallStats $stats): float
    {
        return $stats->totalPurchases > 0
            ? $stats->totalExpenses / $stats->totalPurchases
            : 0.0;
    }

    private function getOverallStats(int $userId, string $start, string $end): OverallStats
    {
        return Cache::remember($this->cacheKey('overall_stats', $userId, $start, $end), 300, function () use ($userId, $start, $end) {
            $result = Invoice::where('user_id', $userId)
                ->whereDateBetween('issued_at', $start, $end)
                ->selectRaw('
                    COALESCE(SUM(total_amount), 0) as totalExpenses,
                    COALESCE(SUM(total_taxes), 0) as totalTaxes,
                    COUNT(id) as totalPurchases
                ')
                ->first();

            return OverallStats::fromQueryResult($result);
        });
    }

    private function getPeriodComparison(int $userId, string $start, string $end, string $previousStart, string $previousEnd): array
    {
        [$periodExpenses, $previousPeriodExpenses] = Cache::remember(
            $this->cacheKey('period_comparison', $userId, $start, $end),
            300,
            function () use ($userId, $start, $end, $previousStart, $previousEnd) {
                $result = Invoice::where('user_id', $userId)
                    ->whereDateBetween('issued_at', $previousStart, $end)
                    ->selectRaw('
                        COALESCE(SUM(CASE WHEN issued_at >= ? THEN total_amount ELSE 0 END), 0) as period,
                        COALESCE(SUM(CASE WHEN issued_at <= ? THEN total_amount ELSE 0 END), 0) as previous_period
                    ', [$start, $previousEnd.' 23:59:59'])
                    ->first();

                return [(float) $result->period, (float) $result->previous_period];
            }
        );

        $periodVariation = $previousPeriodExpenses > 0
            ? (($periodExpenses - $previousPeriodExpenses) / $previousPeriodExpenses) * 100
            : null;

        return [
            'periodExpenses' => $periodExpenses,
            'previousPeriodExpenses' => $previousPeriodExpenses,
            'periodVariation' => $periodVariation,
        ];
    }

    private function getLastPurchase(int $userId, string $start, string $end): ?Invoice
    {
        return Invoice::where('user_id', $userId)
            ->whereDateBetween('issued_at', $start, $end)
            ->select(['id', 'issuer_id', 'issued_at', 'total_amount'])
            ->with('issuer.nicknameForUser')
            ->orderByDesc('issued_at')
            ->first();
    }

    private function getPaymentDistribution(int $userId, string $start, string $end): Collection
    {
        return Cache::remember($this->cacheKey('payment_distribution', $userId, $start, $end), 300, function () use ($userId, $start, $end) {
            return InvoicePayment::join('invoices', 'invoices.id', '=', 'invoices_payments.invoice_id')
                ->where('invoices.user_id', $userId)
                ->whereDateBetween('invoices.issued_at', $start, $end)
                ->select('invoices_payments.method', DB::raw('SUM(invoices_payments.amount) as total'))
                ->groupBy('invoices_payments.method')
                ->orderByDesc('total')
                ->get();
        });
    }

    private function getBudgets(int $userId, string $start, string $end, float $periodExpenses): Collection
    {
        $budgets = Budget::where('user_id', $userId)->with('category')->get();

        $periodSpending = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->whereDateBetween('invoices.issued_at', $start, $end)
            ->select('invoices_items.category_id', DB::raw('SUM(invoices_items.total_price) as total'))
            ->groupBy('invoices_items.category_id')
            ->pluck('total', 'category_id');

        return $budgets->map(function (Budget $budget) use ($periodSpending, $periodExpenses) {
            $budget->spent = $budget->category_id
                ? (float) ($periodSpending[$budget->category_id] ?? 0)
                : (float) $periodExpenses;
            $budget->percentage = $budget->amount > 0 ? ($budget->spent / $budget->amount) * 100 : 0.0;
            $budget->remaining = (float) $budget->amount - $budget->spent;

            return $budget;
        });
    }

    private function getMonthlyExpenses(int $userId, Carbon $now): Collection
    {
        return Cache::remember("dashboard.monthly_expenses.{$userId}", 300, function () use ($userId, $now) {
            return Invoice::where('user_id', $userId)
                ->where('issued_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
                ->select(DB::raw('substr(issued_at, 1, 7) as month'), DB::raw('SUM(total_amount) as total'))
                ->groupBy('month')
                ->orderBy('month')
                ->get();
        });
    }

    private function getSpendingByCategory(int $userId, string $start, string $end): Collection
    {
        return Cache::remember($this->cacheKey('spending_by_category', $userId, $start, $end), 300, function () use ($userId, $start, $end) {
            return InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
                ->where('invoices.user_id', $userId)
                ->whereDateBetween('invoices.issued_at', $start, $end)
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

    private function getTopIssuers(int $userId, string $start, string $end): Collection
    {
        return Cache::remember($this->cacheKey('top_issuers', $userId, $start, $end), 300, function () use ($userId, $start, $end) {
            return Invoice::where('invoices.user_id', $userId)
                ->whereDateBetween('invoices.issued_at', $start, $end)
                ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
                ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                    $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                        ->where('issuer_nicknames.user_id', '=', $userId);
                })
                ->select(
                    DB::raw('COALESCE(issuer_nicknames.nickname, issuers.name) as name'),
                    DB::raw('SUM(invoices.total_amount) as total'),
                    DB::raw('COUNT(invoices.id) as count')
                )
                ->groupBy('issuers.id', 'issuers.name', 'issuer_nicknames.nickname')
                ->orderByDesc('total')
                ->limit(5)
                ->get();
        });
    }

    private function getTopProducts(int $userId, string $start, string $end): Collection
    {
        return Cache::remember($this->cacheKey('top_products', $userId, $start, $end), 300, function () use ($userId, $start, $end) {
            $query = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
                ->where('invoices.user_id', $userId)
                ->whereDateBetween('invoices.issued_at', $start, $end);
            $this->aliasService->joinCanonicalNames($query, $userId);

            $nameSql = $this->aliasService->canonicalNameSql();

            return $query
                ->select(DB::raw("{$nameSql} as description"), DB::raw('COUNT(*) as frequency'), DB::raw('AVG(invoices_items.unit_price) as avg_price'))
                ->groupBy(DB::raw($nameSql))
                ->orderByDesc('frequency')
                ->limit(10)
                ->get();
        });
    }

    private function cacheKey(string $metric, int $userId, string $start, string $end): string
    {
        return "dashboard.{$metric}.{$userId}.{$start}.{$end}";
    }

    private function paymentLabels(): array
    {
        return [
            'dinheiro' => 'Dinheiro',
            'cheque' => 'Cheque',
            'cartao_credito' => 'Cartão de Crédito',
            'cartao_debito' => 'Cartão de Débito',
            'credito_loja' => 'Crédito Loja',
            'vale_alimentacao' => 'Vale Alimentação',
            'vale_refeicao' => 'Vale Refeição',
            'vale_presente' => 'Vale Presente',
            'vale_combustivel' => 'Vale Combustível',
            'boleto' => 'Boleto',
            'sem_pagamento' => 'Sem Pagamento',
            'outros' => 'Outros',
            'pix' => 'Pix',
        ];
    }

    private function paymentIcons(): array
    {
        return [
            'dinheiro' => 'ki-filled ki-dollar',
            'cartao_credito' => 'ki-filled ki-credit-cart',
            'cartao_debito' => 'ki-filled ki-credit-cart',
            'vale_alimentacao' => 'ki-filled ki-basket',
            'vale_refeicao' => 'ki-filled ki-coffee',
            'pix' => 'ki-filled ki-send',
            'boleto' => 'ki-filled ki-document',
        ];
    }
}
