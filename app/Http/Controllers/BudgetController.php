<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $startOfMonth = Carbon::now()->startOfMonth();

        $budgets = Budget::where('user_id', $userId)
            ->with('category')
            ->get();

        $monthlySpendingByCategory = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->where('invoices.issued_at', '>=', $startOfMonth)
            ->select(
                'invoices_items.category_id',
                DB::raw('SUM(invoices_items.total_price) as total')
            )
            ->groupBy('invoices_items.category_id')
            ->pluck('total', 'category_id');

        $totalMonthlySpending = $monthlySpendingByCategory->sum();

        $budgets->each(function ($budget) use ($monthlySpendingByCategory, $totalMonthlySpending) {
            if ($budget->category_id) {
                $budget->spent = (float) ($monthlySpendingByCategory[$budget->category_id] ?? 0);
            } else {
                $budget->spent = (float) $totalMonthlySpending;
            }
            $budget->percentage = $budget->amount > 0 ? ($budget->spent / $budget->amount) * 100 : 0;
            $budget->remaining = max(0, $budget->amount - $budget->spent);
        });

        $categories = Category::forUser($userId)->orderBy('name')->get();

        return view('budget.index', [
            'budgets' => $budgets,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $userId = Auth::id();
        $categoryId = $request->input('category_id');

        $budget = Budget::where('user_id', $userId)
            ->where(fn ($q) => $categoryId
                ? $q->where('category_id', $categoryId)
                : $q->whereNull('category_id')
            )
            ->first();

        if ($budget) {
            $budget->update(['amount' => $request->input('amount')]);
        } else {
            $budget = Budget::create([
                'user_id' => $userId,
                'category_id' => $categoryId,
                'amount' => $request->input('amount'),
            ]);
        }

        return response()->json($budget->load('category'));
    }

    public function destroy(Budget $budget)
    {
        if ($budget->user_id !== Auth::id()) {
            abort(403);
        }

        $budget->delete();

        return response()->json(['success' => true]);
    }
}
