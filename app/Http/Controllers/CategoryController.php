<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InvoiceItem;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();

        $categories = Category::forUser($userId)
            ->withCount(['items' => fn ($q) => $q->whereHas('invoice', fn ($q2) => $q2->where('user_id', $userId))])
            ->get();

        $spendingByCategory = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->whereNotNull('invoices_items.category_id')
            ->select('invoices_items.category_id', DB::raw('SUM(invoices_items.total_price) as total'))
            ->groupBy('invoices_items.category_id')
            ->pluck('total', 'category_id');

        $categories = $categories->map(function (Category $cat) use ($spendingByCategory) {
            $cat->total_spent = (float) ($spendingByCategory[$cat->id] ?? 0);

            return $cat;
        });

        return view('category.index', ['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'keywords' => 'nullable|string',
        ]);

        $category = Category::create([
            'user_id' => Auth::id(),
            'name' => $request->input('name'),
            'color' => $request->input('color', '#94A3B8'),
            'keywords' => $this->parseKeywords($request),
        ]);

        return response()->json($category);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'keywords' => 'nullable|string',
        ]);

        $category->update([
            'name' => $request->input('name'),
            'color' => $request->input('color'),
            'keywords' => $this->parseKeywords($request),
        ]);

        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json(['success' => true]);
    }

    public function assignItem(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|integer|exists:invoices_items,id',
            'category_id' => 'nullable|integer|exists:categories,id',
        ]);

        $item = InvoiceItem::findOrFail($request->input('item_id'));
        abort_if($item->invoice->user_id !== Auth::id(), 403);

        $item->update(['category_id' => $request->input('category_id')]);

        return response()->json(['success' => true]);
    }

    public function autoCategorize(CategoryService $service): JsonResponse
    {
        $count = $service->autoCategorize(Auth::id());

        return response()->json(['categorized' => $count]);
    }

    private function parseKeywords(Request $request): array
    {
        return $request->input('keywords')
            ? array_map('trim', explode(',', $request->input('keywords')))
            : [];
    }
}
