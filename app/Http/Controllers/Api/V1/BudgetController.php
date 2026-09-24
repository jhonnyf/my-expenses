<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StoreBudgetAction;
use App\Http\Requests\BudgetMonthRequest;
use App\Http\Requests\StoreBudgetRequest;
use App\Http\Resources\Api\V1\BudgetResource;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $service) {}

    public function index(BudgetMonthRequest $request): JsonResponse
    {
        $data = $this->service->getBudgetsWithSpending($request->user()->id, $request->month());

        return $this->success([
            'budgets' => BudgetResource::collection($data['budgets']),
            'categories' => CategoryResource::collection($data['categories']),
            'summary' => $data['summary'],
            'month' => $data['month'],
        ]);
    }

    public function store(StoreBudgetRequest $request, StoreBudgetAction $action): JsonResponse
    {
        $budget = $action->execute(
            $request->user(),
            $request->input('category_id'),
            $request->input('amount')
        );

        return $this->success(new BudgetResource($this->service->attachSpending($budget)), 201);
    }

    public function destroy(Budget $budget): JsonResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return response()->json(['message' => 'Orçamento removido com sucesso.']);
    }
}
