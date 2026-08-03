<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFavoriteProductRequest;
use App\Models\FavoriteProduct;
use App\Services\PriceComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FavoriteProductController extends Controller
{
    public function __construct(private readonly PriceComparisonService $service) {}

    public function index(): JsonResponse
    {
        $favorites = FavoriteProduct::where('user_id', Auth::id())->orderBy('canonical_name')->get();

        $favorites->each(function (FavoriteProduct $favorite) {
            $offer = $this->service->cheapestOffer($favorite->canonical_name, Auth::id());
            $favorite->setAttribute('current_offer', $offer);
        });

        return response()->json($favorites);
    }

    public function store(StoreFavoriteProductRequest $request): JsonResponse
    {
        $favorite = FavoriteProduct::updateOrCreate(
            ['user_id' => Auth::id(), 'canonical_name' => $request->input('canonical_name')],
            ['unit' => $request->input('unit')]
        );

        return response()->json($favorite, 201);
    }

    public function destroy(FavoriteProduct $favoriteProduct): JsonResponse
    {
        $this->authorize('interact', $favoriteProduct);

        $favoriteProduct->delete();

        return response()->json(['success' => true]);
    }
}
