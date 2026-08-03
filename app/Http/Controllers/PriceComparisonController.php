<?php

namespace App\Http\Controllers;

use App\Services\PriceComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PriceComparisonController extends Controller
{
    public function __construct(private readonly PriceComparisonService $service) {}

    public function index(): View
    {
        return view('price-comparison.index');
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        return response()->json($this->service->searchProducts($query, Auth::id()));
    }

    public function byCity(Request $request): JsonResponse
    {
        $productName = $request->input('product', '');

        if ($productName === '') {
            return response()->json([]);
        }

        return response()->json($this->service->byCity($productName, Auth::id()));
    }

    public function byIssuer(Request $request): JsonResponse
    {
        $productName = $request->input('product', '');
        $city = $request->input('city', '');
        $state = $request->input('state', '');

        if ($productName === '' || $city === '' || $state === '') {
            return response()->json([]);
        }

        return response()->json($this->service->byIssuer($productName, $city, $state, Auth::id()));
    }
}
