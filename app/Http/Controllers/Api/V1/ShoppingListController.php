<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AddShoppingListItemRequest;
use App\Http\Requests\StoreShoppingListRequest;
use App\Http\Requests\UpdateShoppingListItemRequest;
use App\Http\Requests\UpdateShoppingListRequest;
use App\Http\Resources\Api\V1\ShoppingListItemResource;
use App\Http\Resources\Api\V1\ShoppingListResource;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\ShoppingListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingListController extends Controller
{
    public function __construct(private readonly ShoppingListService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(ShoppingListResource::collection($this->service->listsWithTotals($request->user()->id)));
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return $this->success([]);
        }

        $user = $request->user();
        $favoriteIds = $user->favoriteIssuers()->pluck('issuers.id');

        $filterCity = $request->input('city');
        $filterState = $request->input('state');
        $profile = $filterCity === null && $filterState === null ? $user->profile : null;

        return $this->success($this->service->searchProducts(
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
        return $this->success($this->service->availableCities());
    }

    public function store(StoreShoppingListRequest $request): JsonResponse
    {
        $list = $this->service->createList($request->user()->id, $request->input('name'));

        return $this->success(new ShoppingListResource($list), 201);
    }

    public function show(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        $shoppingList->load('items.issuer.nicknameForUser');

        return $this->success(new ShoppingListResource($shoppingList));
    }

    public function update(UpdateShoppingListRequest $request, ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        $shoppingList->update(['name' => $request->input('name')]);

        return response()->json(['message' => 'Lista atualizada com sucesso.']);
    }

    public function destroy(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        $shoppingList->delete();

        return response()->json(['message' => 'Lista removida com sucesso.']);
    }

    public function duplicate(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        $copy = $this->service->duplicate($shoppingList)->loadCount('items');

        return $this->success(new ShoppingListResource($copy), 201);
    }

    /** 201 quando cria uma linha; 200 quando o produto já estava pendente no mesmo mercado e só somou a quantidade. */
    public function addItem(AddShoppingListItemRequest $request, ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        ['item' => $item, 'merged' => $merged] = $this->service->addItem($shoppingList, $request->validated());

        return $this->success([...(new ShoppingListItemResource($item))->resolve(), 'merged' => $merged], $merged ? 200 : 201);
    }

    public function updateItem(UpdateShoppingListItemRequest $request, ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        $this->service->updateQuantity($shoppingList, $item, (int) $request->input('quantity'));

        return response()->json(['message' => 'Item atualizado com sucesso.']);
    }

    public function removeItem(ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        $this->service->removeItem($shoppingList, $item);

        return response()->json(['message' => 'Item removido com sucesso.']);
    }

    public function togglePurchased(ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        return $this->success(['purchased_at' => $this->service->togglePurchased($shoppingList, $item)->purchased_at]);
    }

    public function purchaseAll(ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        return $this->success(['purchased' => $this->service->markAllPurchased($shoppingList)]);
    }

    public function refreshPrices(Request $request, ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        return $this->success($this->service->refreshPrices($shoppingList, $request->user()->id));
    }

    public function savings(Request $request, ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('interact', $shoppingList);

        return $this->success($this->service->savings($shoppingList, $request->user()));
    }
}
