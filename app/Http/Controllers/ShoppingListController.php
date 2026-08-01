<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddShoppingListItemRequest;
use App\Http\Requests\UpdateShoppingListItemRequest;
use App\Http\Requests\UpdateShoppingListRequest;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\ShoppingListService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShoppingListController extends Controller
{
    public function __construct(private readonly ShoppingListService $service) {}

    public function index(): View
    {
        $lists = ShoppingList::where('user_id', Auth::id())
            ->withCount('items')
            ->withSum(['items as items_total' => fn ($query) => $query], DB::raw('unit_price * quantity'))
            ->orderByDesc('updated_at')
            ->get();

        return view('shopping-list.index', ['lists' => $lists]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $userId = Auth::id();
        $favoriteIds = Auth::user()->favoriteIssuers()->pluck('issuers.id');

        return response()->json($this->service->searchProducts($userId, $query, $favoriteIds));
    }

    public function store(Request $request): JsonResponse
    {
        $name = $request->input('name') ?: 'Lista de compras '.Carbon::now()->format('d/m/Y');

        $list = ShoppingList::create([
            'user_id' => Auth::id(),
            'name' => $name,
        ]);

        return response()->json(['id' => $list->id, 'name' => $list->name]);
    }

    public function show(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        $shoppingList->load('items.issuer.nicknameForUser');
        $shoppingList->items->each(fn (ShoppingListItem $item) => $item->issuer?->append('display_name'));

        return response()->json($shoppingList);
    }

    public function update(UpdateShoppingListRequest $request, ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        $shoppingList->update(['name' => $request->input('name')]);

        return response()->json(['success' => true]);
    }

    public function destroy(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        $shoppingList->delete();

        return response()->json(['success' => true]);
    }

    public function addItem(AddShoppingListItemRequest $request, ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        $item = $shoppingList->items()->create([
            'issuer_id' => $request->input('issuer_id'),
            'description' => $request->input('description'),
            'unit' => $request->input('unit'),
            'unit_price' => $request->input('unit_price'),
            'quantity' => $request->input('quantity'),
        ]);

        $item->load('issuer.nicknameForUser');
        $item->issuer?->append('display_name');
        $shoppingList->touch();

        return response()->json($item);
    }

    public function updateItem(UpdateShoppingListItemRequest $request, ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        $item->update(['quantity' => $request->input('quantity')]);
        $shoppingList->touch();

        return response()->json(['success' => true]);
    }

    public function removeItem(ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        $item->delete();
        $shoppingList->touch();

        return response()->json(['success' => true]);
    }

    public function togglePurchased(ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        $item->purchased_at = $item->purchased_at ? null : Carbon::now();
        $item->save();
        $shoppingList->touch();

        return response()->json(['purchased_at' => $item->purchased_at]);
    }
}
