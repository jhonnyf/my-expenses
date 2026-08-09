<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreFavoriteProductRequest;
use App\Models\FavoriteProduct;
use App\Services\PriceComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteProductController extends Controller
{
    public function __construct(private readonly PriceComparisonService $service) {}

    public function index(Request $request): JsonResponse
    {
        $favorites = FavoriteProduct::where('user_id', $request->user()->id)->orderBy('canonical_name')->get();

        $favorites->each(function (FavoriteProduct $favorite) use ($request) {
            $offer = $this->service->cheapestOffer($favorite->canonical_name, $request->user()->id);
            $favorite->setAttribute('current_offer', $offer);
        });

        return $this->success($favorites);
    }

    public function store(StoreFavoriteProductRequest $request): JsonResponse
    {
        $favorite = FavoriteProduct::updateOrCreate(
            ['user_id' => $request->user()->id, 'canonical_name' => $request->input('canonical_name')],
            ['unit' => $request->input('unit')]
        );

        return $this->success($favorite, 201);
    }

    public function toggle(StoreFavoriteProductRequest $request): JsonResponse
    {
        $favorite = FavoriteProduct::where('user_id', $request->user()->id)
            ->where('canonical_name', $request->input('canonical_name'))
            ->first();

        if ($favorite) {
            $favorite->delete();

            return $this->success(['is_favorite' => false]);
        }

        FavoriteProduct::create([
            'user_id' => $request->user()->id,
            'canonical_name' => $request->input('canonical_name'),
            'unit' => $request->input('unit'),
        ]);

        return $this->success(['is_favorite' => true]);
    }

    public function destroy(Request $request, FavoriteProduct $favoriteProduct): JsonResponse
    {
        $this->authorize('interact', $favoriteProduct);

        $favoriteProduct->delete();

        return response()->json(['message' => 'Favorito removido com sucesso.']);
    }
}
