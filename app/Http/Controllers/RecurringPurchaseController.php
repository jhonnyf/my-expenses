<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToShoppingListRequest;
use App\Models\ShoppingList;
use App\Services\RecurringPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RecurringPurchaseController extends Controller
{
    public function __construct(private readonly RecurringPurchaseService $service) {}

    public function index(): View
    {
        $userId = Auth::id();

        $recurring = $this->service->getRecurringItems($userId);
        $bestIssuers = $this->service->getBestIssuers($userId, $recurring->take(30)->pluck('description'));

        $shoppingLists = ShoppingList::where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->get();

        return view('recurring-purchase.index', [
            'recurring'     => $recurring,
            'bestIssuers'   => $bestIssuers,
            'shoppingLists' => $shoppingLists,
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

        return response()->json(['success' => true, 'item_id' => $item->id]);
    }
}
