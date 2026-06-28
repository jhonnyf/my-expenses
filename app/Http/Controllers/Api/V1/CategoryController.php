<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AssignCategoryItemRequest;
use App\Http\Requests\SaveCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Models\InvoiceItem;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $service) {}

    public function index(Request $request): JsonResponse
    {
        $categories = $this->service->getCategoriesWithSpending($request->user()->id);

        return $this->success(CategoryResource::collection($categories));
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        return $this->success(new CategoryResource($category));
    }

    public function store(SaveCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'user_id'  => $request->user()->id,
            'name'     => $request->input('name'),
            'color'    => $request->input('color', '#94A3B8'),
            'keywords' => $request->parsedKeywords(),
        ]);

        return $this->success(new CategoryResource($category), 201);
    }

    public function update(SaveCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update([
            'name'     => $request->input('name'),
            'color'    => $request->input('color'),
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
        $item = InvoiceItem::findOrFail($request->input('item_id'));
        abort_if($item->invoice->user_id !== $request->user()->id, 403);

        $item->update(['category_id' => $request->input('category_id')]);

        return $this->success(['success' => true]);
    }

    public function autoCategorize(Request $request): JsonResponse
    {
        $count = $this->service->autoCategorize($request->user()->id);

        return $this->success(['categorized' => $count]);
    }
}
