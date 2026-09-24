<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToShoppingListRequest;
use App\Http\Requests\DismissRecurringRequest;
use App\Http\Requests\RecurringFiltersRequest;
use App\Models\ShoppingList;
use App\Services\RecurringPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RecurringPurchaseController extends Controller
{
    public function __construct(private readonly RecurringPurchaseService $service) {}

    public function index(RecurringFiltersRequest $request): View
    {
        $userId = Auth::id();
        $filters = $request->filters();

        $items = $this->service->getRecurringItems($userId);

        return view('recurring-purchase.index', [
            'recurring' => $this->service->filter($items, $filters),
            'summary' => $this->service->summary($items),
            'filters' => $filters,
            'shoppingLists' => ShoppingList::where('user_id', $userId)->orderByDesc('updated_at')->get(['id', 'name']),
        ]);
    }

    public function addToShoppingList(AddToShoppingListRequest $request): JsonResponse
    {
        $list = $request->filled('shopping_list_id') ? ShoppingList::findOrFail($request->input('shopping_list_id')) : null;

        if ($list !== null) {
            $this->authorize('interact', $list);
        }

        $result = $this->service->addToList($request->user(), $list, $request->safe()->except('shopping_list_id'));

        return response()->json([
            'success' => true,
            'item_id' => $result['item']->id,
            'list_id' => $result['list']->id,
            'list_name' => $result['list']->name,
            'merged' => $result['merged'],
        ]);
    }

    public function createReplenishmentList(): JsonResponse
    {
        $created = $this->service->createReplenishmentList(Auth::user());

        if ($created === null) {
            return response()->json(['success' => false, 'message' => 'Nenhum produto está na hora de comprar.'], 422);
        }

        return response()->json([
            'success' => true,
            'list_id' => $created['list']->id,
            'count' => $created['count'],
        ], 201);
    }

    public function dismiss(DismissRecurringRequest $request): JsonResponse
    {
        $this->service->dismiss(Auth::id(), $request->validated('description'));

        return response()->json(['success' => true]);
    }

    public function restore(DismissRecurringRequest $request): JsonResponse
    {
        $this->service->restore(Auth::id(), $request->validated('description'));

        return response()->json(['success' => true]);
    }
}
