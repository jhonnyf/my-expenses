<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AddToShoppingListRequest;
use App\Http\Requests\DismissRecurringRequest;
use App\Http\Requests\RecurringFiltersRequest;
use App\Models\ShoppingList;
use App\Services\RecurringPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringPurchaseController extends Controller
{
    public function __construct(private readonly RecurringPurchaseService $service) {}

    public function index(RecurringFiltersRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        $items = $this->service->getRecurringItems($userId);

        return $this->success([
            'recurring' => $this->service->filter($items, $request->filters()),
            'summary' => $this->service->summary($items),
            'shopping_lists' => ShoppingList::where('user_id', $userId)->orderByDesc('updated_at')->get(['id', 'name']),
        ]);
    }

    public function addToShoppingList(AddToShoppingListRequest $request): JsonResponse
    {
        $list = $request->filled('shopping_list_id') ? ShoppingList::findOrFail($request->input('shopping_list_id')) : null;

        if ($list !== null) {
            $this->authorize('interact', $list);
        }

        $result = $this->service->addToList($request->user(), $list, $request->safe()->except('shopping_list_id'));

        return $this->success([
            'item_id' => $result['item']->id,
            'list_id' => $result['list']->id,
            'list_name' => $result['list']->name,
            'merged' => $result['merged'],
        ], 201);
    }

    public function createReplenishmentList(Request $request): JsonResponse
    {
        $created = $this->service->createReplenishmentList($request->user());

        if ($created === null) {
            return $this->error('Nenhum produto está na hora de comprar.', 422);
        }

        return $this->success(['list_id' => $created['list']->id, 'count' => $created['count']], 201);
    }

    public function dismiss(DismissRecurringRequest $request): JsonResponse
    {
        $this->service->dismiss($request->user()->id, $request->validated('description'));

        return $this->success(['dismissed' => true]);
    }

    public function restore(DismissRecurringRequest $request): JsonResponse
    {
        $this->service->restore($request->user()->id, $request->validated('description'));

        return $this->success(['dismissed' => false]);
    }
}
