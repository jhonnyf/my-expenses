<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $service) {}

    public function index(): View
    {
        return view('budget.index', $this->service->getBudgetsWithSpending(Auth::id()));
    }

    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $budget = Budget::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'category_id' => $request->input('category_id'),
            ],
            ['amount' => $request->input('amount')]
        )->load('category');

        $budget = $this->service->attachSpending($budget);

        return response()->json([
            ...$budget->toArray(),
            'spent' => $budget->spent,
            'percentage' => $budget->percentage,
            'remaining' => $budget->remaining,
        ]);
    }

    public function destroy(Budget $budget): JsonResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return response()->json(['success' => true]);
    }
}
