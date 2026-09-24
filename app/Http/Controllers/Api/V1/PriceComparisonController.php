<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PriceQueryRequest;
use App\Services\PriceComparisonService;
use Illuminate\Http\JsonResponse;

class PriceComparisonController extends Controller
{
    private const MIN_SEARCH_LENGTH = 2;

    public function __construct(private readonly PriceComparisonService $service) {}

    public function searchProducts(PriceQueryRequest $request): JsonResponse
    {
        $query = $request->searchTerm();

        if (mb_strlen($query) < self::MIN_SEARCH_LENGTH) {
            return $this->success([]);
        }

        return $this->success($this->service->searchProducts($query, $request->user()->id));
    }

    public function units(PriceQueryRequest $request): JsonResponse
    {
        $product = $request->text('product');

        return $this->success($product === '' ? [] : $this->service->units($product, $request->user()->id));
    }

    public function byCity(PriceQueryRequest $request): JsonResponse
    {
        $product = $request->text('product');

        if ($product === '') {
            return $this->success([]);
        }

        $profile = $request->user()->profile;

        return $this->success($this->service->byCity($product, $request->user()->id, $request->unit(), $profile?->cidade, $profile?->estado));
    }

    public function byIssuer(PriceQueryRequest $request): JsonResponse
    {
        $product = $request->text('product');
        $city = $request->text('city');
        $state = $request->text('state');

        if ($product === '' || $city === '' || $state === '') {
            return $this->success([]);
        }

        $profile = $request->user()->profile;

        return $this->success($this->service->byIssuer(
            $product,
            $city,
            $state,
            $request->user()->id,
            $request->unit(),
            $profile?->latitude,
            $profile?->longitude,
        ));
    }
}
