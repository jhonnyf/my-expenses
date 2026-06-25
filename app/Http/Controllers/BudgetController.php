<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();
        $startOfMonth = Carbon::now()->startOfMonth();

        $budgets = Budget::where('user_id', $userId)
            ->with('category')
            ->get();

        $monthlySpendingByCategory = $this->getMonthlySpendingByCategory($userId, $startOfMonth);
        $totalMonthlySpending = $monthlySpendingByCategory->sum();

        $budgets = $this->applySpendingToBudgets($budgets, $monthlySpendingByCategory, $totalMonthlySpending);

        $categories = Category::forUser($userId)->orderBy('name')->get();

        return view('budget.index', [
            'budgets' => $budgets,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $userId = Auth::id();
        $categoryId = $request->input('category_id');

        $budget = Budget::updateOrCreate(
            [
                'user_id' => $userId,
                'category_id' => $categoryId,
            ],
            ['amount' => $request->input('amount')]
        );

        return response()->json($budget->load('category'));
    }

    public function destroy(Budget $budget): JsonResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return response()->json(['success' => true]);
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
        });
    }
}
