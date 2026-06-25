<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignCategoryItemRequest;
use App\Http\Requests\SaveCategoryRequest;
use App\Models\Category;
use App\Models\InvoiceItem;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $service) {}

    public function index(): View
    {
        return view('category.index', [
            'categories' => $this->service->getCategoriesWithSpending(Auth::id()),
        ]);
    }

    public function store(SaveCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'user_id'  => Auth::id(),
            'name'     => $request->input('name'),
            'color'    => $request->input('color', '#94A3B8'),
            'keywords' => $request->parsedKeywords(),
        ]);

        return response()->json($category);
    }

    public function update(SaveCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update([
            'name'     => $request->input('name'),
            'color'    => $request->input('color'),
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
        $item = InvoiceItem::findOrFail($request->input('item_id'));
        abort_if($item->invoice->user_id !== Auth::id(), 403);

        $item->update(['category_id' => $request->input('category_id')]);

        return response()->json(['success' => true]);
    }

    public function autoCategorize(): JsonResponse
    {
        $count = $this->service->autoCategorize(Auth::id());

        return response()->json(['categorized' => $count]);
    }
}
