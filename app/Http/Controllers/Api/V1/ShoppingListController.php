<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AddShoppingListItemRequest;
use App\Http\Requests\UpdateShoppingListItemRequest;
use App\Http\Requests\UpdateShoppingListRequest;
use App\Http\Resources\Api\V1\ShoppingListItemResource;
use App\Http\Resources\Api\V1\ShoppingListResource;
use App\Models\InvoiceItem;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingListController extends Controller
{
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

        $items = InvoiceItem::select(
            'invoices_items.description',
            'invoices_items.unit_price',
            'invoices_items.unit',
            'invoices_items.code',
            'issuers.id as issuer_id',
            'invoices.issued_at'
        )
            ->selectRaw('COALESCE(issuer_nicknames.nickname, issuers.name) as issuer_name')
            ->selectRaw('CASE WHEN issuers.id IN ('.($favoriteIds->isNotEmpty() ? $favoriteIds->implode(',') : '0').') THEN 1 ELSE 0 END as is_favorite')
            ->join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('issuer_nicknames', function ($join) use ($user) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $user->id);
            })
            ->where('invoices.user_id', $user->id)
            ->where('invoices_items.description', 'like', "%{$query}%")
            ->orderByDesc('is_favorite')
            ->orderBy('invoices_items.unit_price', 'asc')
            ->limit(20)
            ->get();

        return $this->success($items);
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
