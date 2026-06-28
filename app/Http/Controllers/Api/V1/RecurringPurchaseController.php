<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AddToShoppingListRequest;
use App\Models\ShoppingList;
use App\Services\RecurringPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringPurchaseController extends Controller
{
    public function __construct(private readonly RecurringPurchaseService $service) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $recurring   = $this->service->getRecurringItems($userId);
        $bestIssuers = $this->service->getBestIssuers($userId, $recurring->take(30)->pluck('description'));

        $shoppingLists = ShoppingList::where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->get(['id', 'name']);

        return $this->success([
            'recurring'      => $recurring,
            'best_issuers'   => $bestIssuers,
            'shopping_lists' => $shoppingLists,
        ]);
    }

    public function addToShoppingList(AddToShoppingListRequest $request): JsonResponse
    {
        $shoppingList = ShoppingList::findOrFail($request->input('shopping_list_id'));
        $this->authorize('interact', $shoppingList);

        $item = $shoppingList->items()->create([
            'description' => $request->input('description'),
            'unit_price'  => $request->input('unit_price'),
            'issuer_id'   => $request->input('issuer_id'),
            'unit'        => $request->input('unit'),
            'quantity'    => 1,
        ]);

        return $this->success(['item_id' => $item->id], 201);
    }
}
