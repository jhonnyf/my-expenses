<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function getBudgetsWithSpending(int $userId): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $budgets = Budget::where('user_id', $userId)->with('category')->get();
        $monthlySpending = $this->getMonthlySpendingByCategory($userId, $startOfMonth);
        $budgets = $this->applySpendingToBudgets($budgets, $monthlySpending, $monthlySpending->sum());

        return [
            'budgets' => $budgets,
            'categories' => Category::forUser($userId)->orderBy('name')->get(),
            'summary' => [
                'total_budgeted' => (float) $budgets->sum('amount'),
                'total_spent' => (float) $budgets->sum('spent'),
                'total_remaining' => (float) $budgets->sum('remaining'),
                'over_budget_count' => $budgets->where('percentage', '>=', 100)->count(),
            ],
        ];
    }

    public function attachSpending(Budget $budget): Budget
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $query = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $budget->user_id)
            ->where('invoices.issued_at', '>=', $startOfMonth);

        if ($budget->category_id) {
            $query->where('invoices_items.category_id', $budget->category_id);
        }

        $spent = (float) $query->sum('invoices_items.total_price');

        $budget->spent = $spent;
        $budget->percentage = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0.0;
        $budget->remaining = max(0.0, (float) $budget->amount - $spent);

        return $budget;
    }

    private function getMonthlySpendingByCategory(int $userId, Carbon $startOfMonth): Collection
    {
        return InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->where('invoices.issued_at', '>=', $startOfMonth)
            ->select(
                'invoices_items.category_id',
                DB::raw('SUM(invoices_items.total_price) as total')
            )
            ->groupBy('invoices_items.category_id')
            ->pluck('total', 'category_id');
    }

    private function applySpendingToBudgets(Collection $budgets, Collection $monthlySpending, float $totalMonthlySpending): Collection
    {
        return $budgets->map(function (Budget $budget) use ($monthlySpending, $totalMonthlySpending) {
            $budget->spent = $budget->category_id
                ? (float) ($monthlySpending[$budget->category_id] ?? 0)
                : (float) $totalMonthlySpending;
            $budget->percentage = $budget->amount > 0 ? ($budget->spent / $budget->amount) * 100 : 0.0;
            $budget->remaining = max(0.0, (float) $budget->amount - $budget->spent);

            return $budget;
        })->sortByDesc('percentage')->values();
    }
}
