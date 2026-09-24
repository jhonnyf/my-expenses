<?php

namespace App\Http\Controllers;

use App\Http\Requests\PriceQueryRequest;
use App\Models\FavoriteProduct;
use App\Models\ShoppingList;
use App\Services\PriceComparisonService;
use App\Services\PriceHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PricesController extends Controller
{
    private const MIN_SEARCH_LENGTH = 2;

    public function __construct(
        private readonly PriceHistoryService $historyService,
        private readonly PriceComparisonService $comparisonService,
    ) {}

    public function index(): View
    {
        return view('prices.index', [
            // Listas para o "adicionar à lista de compras" a partir de um preço do comparativo.
            'shoppingLists' => ShoppingList::where('user_id', Auth::id())->orderByDesc('updated_at')->get(['id', 'name']),
            'favoriteProducts' => FavoriteProduct::where('user_id', Auth::id())->pluck('canonical_name'),
        ]);
    }

    public function search(PriceQueryRequest $request): JsonResponse
    {
        $query = $request->searchTerm();

        if (mb_strlen($query) < self::MIN_SEARCH_LENGTH) {
            return response()->json([]);
        }

        return response()->json($this->comparisonService->searchProducts($query, Auth::id()));
    }

    public function history(PriceQueryRequest $request): JsonResponse
    {
        $description = $request->text('description');

        if ($description === '') {
            return response()->json([]);
        }

        return response()->json($this->historyService->getTimeline($description, Auth::id(), $request->unit()));
    }

    public function units(PriceQueryRequest $request): JsonResponse
    {
        $product = $request->text('product');

        return response()->json($product === '' ? [] : $this->comparisonService->units($product, Auth::id()));
    }

    public function byCity(PriceQueryRequest $request): JsonResponse
    {
        $product = $request->text('product');

        if ($product === '') {
            return response()->json([]);
        }

        $profile = Auth::user()->profile;

        return response()->json($this->comparisonService->byCity($product, Auth::id(), $request->unit(), $profile?->cidade, $profile?->estado));
    }

    public function byIssuer(PriceQueryRequest $request): JsonResponse
    {
        $product = $request->text('product');
        $city = $request->text('city');
        $state = $request->text('state');

        if ($product === '' || $city === '' || $state === '') {
            return response()->json([]);
        }

        $profile = Auth::user()->profile;

        return response()->json($this->comparisonService->byIssuer(
            $product,
            $city,
            $state,
            Auth::id(),
            $request->unit(),
            $profile?->latitude,
            $profile?->longitude,
        ));
    }
}
