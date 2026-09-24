<?php

namespace App\Http\Controllers;

use App\Actions\MergeCategoriesAction;
use App\Actions\SuggestItemCategoryAction;
use App\Http\Requests\AiSuggestCategoryKeywordsRequest;
use App\Http\Requests\AiSuggestItemCategoryRequest;
use App\Http\Requests\AssignCategoryItemRequest;
use App\Http\Requests\CategoryPeriodRequest;
use App\Http\Requests\MergeCategoryRequest;
use App\Http\Requests\PreviewCategoryKeywordsRequest;
use App\Http\Requests\SaveCategoryRequest;
use App\Jobs\AiCategorizeItemsJob;
use App\Models\Category;
use App\Services\BudgetService;
use App\Services\CategoryKeywordsAiSuggestionService;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $service,
        private readonly CategoryKeywordsAiSuggestionService $aiSuggestionService,
        private readonly BudgetService $budgets,
    ) {}

    public function index(CategoryPeriodRequest $request): View
    {
        $userId = Auth::id();
        $filters = $request->period();
        $categories = $this->service->getCategoriesWithSpending($userId, $filters['start_date'], $filters['end_date']);

        return view('category.index', [
            'categories' => $categories,
            'totalSpent' => $categories->sum('total_spent'),
            'topCategory' => $categories->firstWhere('total_spent', '>', 0),
            'uncategorized' => $this->service->uncategorizedSummary($userId, $filters['start_date'], $filters['end_date']),
            'uncategorizedCount' => $this->service->countUncategorizedItems($userId, $filters['start_date'], $filters['end_date']),
            'autoCategorizedCount' => $this->service->countAutoCategorized($userId),
            'budgetsByCategory' => collect($this->budgets->getBudgetsWithSpending($userId)['budgets'])->keyBy('category_id'),
            'filters' => array_filter($filters),
        ]);
    }

    public function show(CategoryPeriodRequest $request, Category $category): View
    {
        $this->authorize('view', $category);

        $userId = Auth::id();
        $period = $request->period();

        return view('category.show', [
            'category' => $category,
            'detail' => $this->service->detail($userId, $category, $period['start_date'], $period['end_date']),
            'budget' => collect($this->budgets->getBudgetsWithSpending($userId)['budgets'])->firstWhere('category_id', $category->id),
            'filters' => [
                'start_date' => $period['start_date'] ?? now()->startOfMonth()->format('Y-m-d'),
                'end_date' => $period['end_date'] ?? now()->format('Y-m-d'),
            ],
        ]);
    }

    public function uncategorized(CategoryPeriodRequest $request): View
    {
        $userId = Auth::id();
        $period = $request->period();

        return view('category.uncategorized', [
            'items' => $this->service->paginateUncategorized($userId, $period['start_date'], $period['end_date']),
            'summary' => $this->service->uncategorizedSummary($userId, $period['start_date'], $period['end_date']),
            'categories' => Category::forUser($userId)->orderBy('name')->get(),
            'filters' => [
                'start_date' => $period['start_date'] ?? now()->startOfMonth()->format('Y-m-d'),
                'end_date' => $period['end_date'] ?? now()->format('Y-m-d'),
            ],
        ]);
    }

    public function store(SaveCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'user_id' => Auth::id(),
            'name' => $request->input('name'),
            'color' => $request->input('color') ?: '#94A3B8',
            'keywords' => $request->parsedKeywords(),
        ]);

        return response()->json($category);
    }

    public function update(SaveCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update([
            'name' => $request->input('name'),
            'color' => $request->input('color', $category->color),
            'keywords' => $request->parsedKeywords(),
        ]);

        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json(['success' => true]);
    }

    public function assignItem(AssignCategoryItemRequest $request): JsonResponse
    {
        $item = $this->service->findItemForUser(Auth::id(), (int) $request->input('item_id'));

        $this->service->assignItem($item, $request->input('category_id'));

        return response()->json(['success' => true]);
    }

    public function autoCategorize(): JsonResponse
    {
        $count = $this->service->autoCategorize(Auth::id());
        AiCategorizeItemsJob::dispatch(Auth::id());

        // A IA (só Pro) roda depois, em segundo plano: o número acima é só de regras e palavras-chave.
        return response()->json(['categorized' => $count, 'ai_pending' => Auth::user()->isPro()]);
    }

    public function revertAutoCategorization(): JsonResponse
    {
        return response()->json(['reverted' => $this->service->revertAutoCategorization(Auth::id())]);
    }

    public function previewKeywords(PreviewCategoryKeywordsRequest $request): JsonResponse
    {
        return response()->json($this->service->previewKeywords(Auth::id(), $request->parsedKeywords()));
    }

    public function merge(MergeCategoryRequest $request, Category $category, MergeCategoriesAction $action): JsonResponse
    {
        $this->authorize('delete', $category);

        $target = Category::forUser(Auth::id())->findOrFail($request->input('target_id'));

        return response()->json(['success' => true, 'moved' => $action->execute($category, $target, Auth::id())]);
    }

    public function suggestKeywords(AiSuggestCategoryKeywordsRequest $request): JsonResponse
    {
        $suggestion = $this->aiSuggestionService->suggestKeywords($request->input('name'));

        return response()->json($suggestion->toArray());
    }

    public function suggestItemCategory(AiSuggestItemCategoryRequest $request, SuggestItemCategoryAction $action): JsonResponse
    {
        $item = $this->service->findItemForUser(Auth::id(), (int) $request->input('item_id'));

        $categoryId = $action->execute($item);

        return response()->json(['category_id' => $categoryId]);
    }
}
