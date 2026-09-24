<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddShoppingListItemRequest;
use App\Http\Requests\StoreShoppingListRequest;
use App\Http\Requests\UpdateShoppingListItemRequest;
use App\Http\Requests\UpdateShoppingListRequest;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\ShoppingListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ShoppingListController extends Controller
{
    public function __construct(private readonly ShoppingListService $service) {}

    public function index(): View
    {
        $profile = Auth::user()->profile;

        return view('shopping-list.index', [
            'lists' => $this->service->listsWithTotals(Auth::id()),
            'profileCity' => $profile?->cidade,
            'profileState' => $profile?->estado,
            'cities' => $this->service->availableCities(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $user = Auth::user();
        $favoriteIds = $user->favoriteIssuers()->pluck('issuers.id');

        $filterCity = $request->input('city');
        $filterState = $request->input('state');
        $profile = $filterCity === null && $filterState === null ? $user->profile : null;

        return response()->json($this->service->searchProducts(
            $user->id,
            $query,
            $favoriteIds,
            $filterCity ?? $profile?->cidade,
            $filterState ?? $profile?->estado,
            $profile?->latitude,
            $profile?->longitude
        ));
    }

    public function cities(): JsonResponse
    {
        return response()->json($this->service->availableCities());
    }

    public function store(StoreShoppingListRequest $request): JsonResponse
    {
        $list = $this->service->createList(Auth::id(), $request->input('name'));

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

    public function duplicate(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        $copy = $this->service->duplicate($shoppingList);

        return response()->json(['id' => $copy->id, 'name' => $copy->name]);
    }

    public function addItem(AddShoppingListItemRequest $request, ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        ['item' => $item, 'merged' => $merged] = $this->service->addItem($shoppingList, $request->validated());
        $item->issuer?->append('display_name');

        return response()->json([...$item->toArray(), 'merged' => $merged]);
    }

    public function updateItem(UpdateShoppingListItemRequest $request, ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        $this->service->updateQuantity($shoppingList, $item, (int) $request->input('quantity'));

        return response()->json(['success' => true]);
    }

    public function removeItem(ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        $this->service->removeItem($shoppingList, $item);

        return response()->json(['success' => true]);
    }

    public function togglePurchased(ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        return response()->json(['purchased_at' => $this->service->togglePurchased($shoppingList, $item)->purchased_at]);
    }

    public function purchaseAll(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        return response()->json(['purchased' => $this->service->markAllPurchased($shoppingList)]);
    }

    public function refreshPrices(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        return response()->json($this->service->refreshPrices($shoppingList, Auth::id()));
    }

    public function savings(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        return response()->json($this->service->savings($shoppingList, Auth::user()));
    }
}
