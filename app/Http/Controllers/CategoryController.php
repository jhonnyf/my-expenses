<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InvoiceItem;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $categories = Category::forUser($userId)
            ->withCount(['items' => fn ($q) => $q->whereHas('invoice', fn ($q2) => $q2->where('user_id', $userId))])
            ->get()
            ->map(function ($category) use ($userId) {
                $category->total_spent = InvoiceItem::where('category_id', $category->id)
                    ->whereHas('invoice', fn ($q) => $q->where('user_id', $userId))
                    ->sum('total_price');

                return $category;
            });

        return view('category.index', ['categories' => $categories]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'keywords' => 'nullable|string',
        ]);

        $keywords = $request->input('keywords')
            ? array_map('trim', explode(',', $request->input('keywords')))
            : [];

        $category = Category::create([
            'user_id' => Auth::id(),
            'name' => $request->input('name'),
            'color' => $request->input('color', '#94A3B8'),
            'keywords' => $keywords,
        ]);

        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        if ($category->user_id && $category->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'keywords' => 'nullable|string',
        ]);

        $keywords = $request->input('keywords')
            ? array_map('trim', explode(',', $request->input('keywords')))
            : [];

        $category->update([
            'name' => $request->input('name'),
            'color' => $request->input('color'),
            'keywords' => $keywords,
        ]);

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        if (! $category->user_id || $category->user_id !== Auth::id()) {
            abort(403);
        }

        $category->delete();

        return response()->json(['success' => true]);
    }

    public function assignItem(Request $request)
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

    public function autoCategorize(CategoryService $service)
    {
        $count = $service->autoCategorize(Auth::id());

        return response()->json(['categorized' => $count]);
    }
}
