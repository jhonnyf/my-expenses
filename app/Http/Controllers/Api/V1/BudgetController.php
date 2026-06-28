<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Resources\Api\V1\BudgetResource;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $service) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->service->getBudgetsWithSpending($request->user()->id);

        return $this->success([
            'budgets'    => BudgetResource::collection($data['budgets']),
            'categories' => CategoryResource::collection($data['categories']),
        ]);
    }

    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $budget = Budget::updateOrCreate(
            [
                'user_id'     => $request->user()->id,
                'category_id' => $request->input('category_id'),
            ],
            ['amount' => $request->input('amount')]
        );

        return $this->success(new BudgetResource($budget->load('category')), 201);
    }

    public function destroy(Budget $budget): JsonResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return response()->json(['message' => 'Orçamento removido com sucesso.']);
    }
}
