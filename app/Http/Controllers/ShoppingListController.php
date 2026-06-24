<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItem;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShoppingListController extends Controller
{
    public function index()
    {
        $lists = ShoppingList::where('user_id', Auth::id())
            ->withCount('items')
            ->orderByDesc('updated_at')
            ->get();

        return view('shopping-list.index', ['lists' => $lists]);
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $userId = Auth::id();
        $favoriteIds = Auth::user()->favoriteIssuers()->pluck('issuers.id');

        $items = InvoiceItem::select(
                'invoices_items.description',
                'invoices_items.unit_price',
                'invoices_items.unit',
                'invoices_items.code',
                'issuers.name as issuer_name',
                'issuers.id as issuer_id',
                'invoices.issued_at'
            )
            ->selectRaw('IF(issuers.id IN (' . ($favoriteIds->isNotEmpty() ? $favoriteIds->implode(',') : '0') . '), 1, 0) as is_favorite')
            ->join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->where('invoices.user_id', $userId)
            ->where('invoices_items.description', 'like', "%{$query}%")
            ->orderByDesc('is_favorite')
            ->orderBy('invoices_items.unit_price', 'asc')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $name = $request->input('name') ?: 'Lista de compras ' . Carbon::now()->format('d/m/Y');

        $list = ShoppingList::create([
            'user_id' => Auth::id(),
            'name' => $name,
        ]);

        return response()->json(['id' => $list->id, 'name' => $list->name]);
    }

    public function show(ShoppingList $shoppingList)
    {
        if ($shoppingList->user_id !== Auth::id()) {
            abort(403);
        }

        $shoppingList->load('items.issuer');

        return response()->json($shoppingList);
    }

    public function update(Request $request, ShoppingList $shoppingList)
    {
        if ($shoppingList->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate(['name' => 'required|string|max:255']);
        $shoppingList->update(['name' => $request->input('name')]);

        return response()->json(['success' => true]);
    }

    public function destroy(ShoppingList $shoppingList)
    {
        if ($shoppingList->user_id !== Auth::id()) {
            abort(403);
        }

        $shoppingList->delete();

        return response()->json(['success' => true]);
    }

    public function addItem(Request $request, ShoppingList $shoppingList)
    {
        if ($shoppingList->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'description' => 'required|string',
            'unit_price' => 'required|numeric',
            'issuer_id' => 'required|integer|exists:issuers,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $item = $shoppingList->items()->create([
            'issuer_id' => $request->input('issuer_id'),
            'description' => $request->input('description'),
            'unit' => $request->input('unit'),
            'unit_price' => $request->input('unit_price'),
            'quantity' => $request->input('quantity'),
        ]);

        $item->load('issuer');
        $shoppingList->touch();

        return response()->json($item);
    }

    public function updateItem(Request $request, ShoppingList $shoppingList, ShoppingListItem $item)
    {
        if ($shoppingList->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate(['quantity' => 'required|integer|min:1']);
        $item->update(['quantity' => $request->input('quantity')]);
        $shoppingList->touch();

        return response()->json(['success' => true]);
    }

    public function removeItem(ShoppingList $shoppingList, ShoppingListItem $item)
    {
        if ($shoppingList->user_id !== Auth::id()) {
            abort(403);
        }

        $item->delete();
        $shoppingList->touch();

        return response()->json(['success' => true]);
    }

    public function togglePurchased(ShoppingList $shoppingList, ShoppingListItem $item)
    {
        if ($shoppingList->user_id !== Auth::id()) {
            abort(403);
        }

        $item->purchased_at = $item->purchased_at ? null : Carbon::now();
        $item->save();
        $shoppingList->touch();

        return response()->json(['purchased_at' => $item->purchased_at]);
    }
}
