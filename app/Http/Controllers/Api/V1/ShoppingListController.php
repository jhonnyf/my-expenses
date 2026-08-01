<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AddShoppingListItemRequest;
use App\Http\Requests\UpdateShoppingListItemRequest;
use App\Http\Requests\UpdateShoppingListRequest;
use App\Http\Resources\Api\V1\ShoppingListItemResource;
use App\Http\Resources\Api\V1\ShoppingListResource;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\ShoppingListService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingListController extends Controller
{
    public function __construct(private readonly ShoppingListService $service) {}

    public function index(Request $request): JsonResponse
    {
        $lists = ShoppingList::where('user_id', $request->user()->id)
            ->withCount('items')
            ->orderByDesc('updated_at')
            ->get();

        return $this->success(ShoppingListResource::collection($lists));
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return $this->success([]);
        }

        $user = $request->user();
        $favoriteIds = $user->favoriteIssuers()->pluck('issuers.id');

        return $this->success($this->service->searchProducts($user->id, $query, $favoriteIds));
    }

    public function store(Request $request): JsonResponse
    {
        $name = $request->input('name') ?: 'Lista de compras '.Carbon::now()->format('d/m/Y');

        $list = ShoppingList::create([
            'user_id' => $request->user()->id,
            'name' => $name,
        ]);

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
        $shoppingList->touch();

        return $this->success(new ShoppingListItemResource($item), 201);
    }

    public function updateItem(UpdateShoppingListItemRequest $request, ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        $item->update(['quantity' => $request->input('quantity')]);
        $shoppingList->touch();

        return response()->json(['message' => 'Item atualizado com sucesso.']);
    }

    public function removeItem(ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        $item->delete();
        $shoppingList->touch();

        return response()->json(['message' => 'Item removido com sucesso.']);
    }

    public function togglePurchased(ShoppingList $shoppingList, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('interact', $shoppingList);
        abort_if($item->shopping_list_id !== $shoppingList->id, 404);

        $item->purchased_at = $item->purchased_at ? null : Carbon::now();
        $item->save();
        $shoppingList->touch();

        return $this->success(['purchased_at' => $item->purchased_at]);
    }
}
