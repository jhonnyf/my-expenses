<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PriceComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceComparisonController extends Controller
{
    public function __construct(private readonly PriceComparisonService $service) {}

    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return $this->success([]);
        }

        return $this->success($this->service->searchProducts($query, $request->user()->id));
    }

    public function byCity(Request $request): JsonResponse
    {
        $productName = $request->input('product', '');

        if ($productName === '') {
            return $this->success([]);
        }

        return $this->success($this->service->byCity($productName, $request->user()->id));
    }

    public function byIssuer(Request $request): JsonResponse
    {
        $productName = $request->input('product', '');
        $city = $request->input('city', '');
        $state = $request->input('state', '');

        if ($productName === '' || $city === '' || $state === '') {
            return $this->success([]);
        }

        return $this->success($this->service->byIssuer($productName, $city, $state, $request->user()->id));
    }
}
