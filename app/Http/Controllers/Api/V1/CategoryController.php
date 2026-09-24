<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\MergeCategoriesAction;
use App\Actions\SuggestItemCategoryAction;
use App\Http\Requests\AiSuggestCategoryKeywordsRequest;
use App\Http\Requests\AiSuggestItemCategoryRequest;
use App\Http\Requests\AssignCategoryItemRequest;
use App\Http\Requests\CategoryPeriodRequest;
use App\Http\Requests\MergeCategoryRequest;
use App\Http\Requests\PreviewCategoryKeywordsRequest;
use App\Http\Requests\SaveCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\UncategorizedItemResource;
use App\Jobs\AiCategorizeItemsJob;
use App\Models\Category;
use App\Services\CategoryKeywordsAiSuggestionService;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $service,
        private readonly CategoryKeywordsAiSuggestionService $aiSuggestionService,
    ) {}

    public function index(CategoryPeriodRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        ['start_date' => $startDate, 'end_date' => $endDate] = $request->period();

        $categories = $this->service->getCategoriesWithSpending($userId, $startDate, $endDate);
        $uncategorized = $this->service->uncategorizedSummary($userId, $startDate, $endDate);

        return response()->json([
            'data' => CategoryResource::collection($categories),
            'meta' => [
                'uncategorizedCount' => $uncategorized['count'],
                'uncategorizedTotal' => $uncategorized['total'],
                'autoCategorizedCount' => $this->service->countAutoCategorized($userId),
            ],
        ]);
    }

    public function show(CategoryPeriodRequest $request, Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        ['start_date' => $startDate, 'end_date' => $endDate] = $request->period();

        return $this->success([
            ...(new CategoryResource($category))->resolve(),
            'insights' => $this->service->detail($request->user()->id, $category, $startDate, $endDate),
        ]);
    }

    public function uncategorized(CategoryPeriodRequest $request): JsonResponse
    {
        ['start_date' => $startDate, 'end_date' => $endDate] = $request->period();
        $userId = $request->user()->id;

        return UncategorizedItemResource::collection(
            $this->service->paginateUncategorized($userId, $startDate, $endDate)
        )->additional(['meta_summary' => $this->service->uncategorizedSummary($userId, $startDate, $endDate)])->response();
    }

    public function store(SaveCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'user_id' => $request->user()->id,
            'name' => $request->input('name'),
            'color' => $request->input('color') ?: '#94A3B8',
            'keywords' => $request->parsedKeywords(),
        ]);

        return $this->success(new CategoryResource($category), 201);
    }

    public function update(SaveCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update([
            'name' => $request->input('name'),
            'color' => $request->input('color', $category->color),
            'keywords' => $request->parsedKeywords(),
        ]);

        return $this->success(new CategoryResource($category));
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json(['message' => 'Categoria removida com sucesso.']);
    }

    public function assignItem(AssignCategoryItemRequest $request): JsonResponse
    {
        $item = $this->service->findItemForUser($request->user()->id, (int) $request->input('item_id'));

        $this->service->assignItem($item, $request->input('category_id'));

        return $this->success(['success' => true]);
    }

    public function autoCategorize(Request $request): JsonResponse
    {
        $count = $this->service->autoCategorize($request->user()->id);
        AiCategorizeItemsJob::dispatch($request->user()->id);

        // A IA (só Pro) roda depois, em segundo plano: o número acima é só de regras e palavras-chave.
        return $this->success(['categorized' => $count, 'ai_pending' => $request->user()->isPro()]);
    }

    public function revertAutoCategorization(Request $request): JsonResponse
    {
        return $this->success(['reverted' => $this->service->revertAutoCategorization($request->user()->id)]);
    }

    public function previewKeywords(PreviewCategoryKeywordsRequest $request): JsonResponse
    {
        return $this->success($this->service->previewKeywords($request->user()->id, $request->parsedKeywords()));
    }

    public function merge(MergeCategoryRequest $request, Category $category, MergeCategoriesAction $action): JsonResponse
    {
        $this->authorize('delete', $category);

        $userId = $request->user()->id;
        $target = Category::forUser($userId)->findOrFail($request->input('target_id'));

        return $this->success(['success' => true, 'moved' => $action->execute($category, $target, $userId)]);
    }

    public function suggestKeywords(AiSuggestCategoryKeywordsRequest $request): JsonResponse
    {
        $suggestion = $this->aiSuggestionService->suggestKeywords($request->input('name'));

        return $this->success($suggestion->toArray());
    }

    public function suggestItemCategory(AiSuggestItemCategoryRequest $request, SuggestItemCategoryAction $action): JsonResponse
    {
        $item = $this->service->findItemForUser($request->user()->id, (int) $request->input('item_id'));

        $categoryId = $action->execute($item);

        return $this->success(['category_id' => $categoryId]);
    }
}
