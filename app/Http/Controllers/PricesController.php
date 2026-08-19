<?php

namespace App\Http\Controllers;

use App\Services\PriceComparisonService;
use App\Services\PriceHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PricesController extends Controller
{
    public function __construct(
        private readonly PriceHistoryService $historyService,
        private readonly PriceComparisonService $comparisonService,
    ) {}

    public function index(): View
    {
        return view('prices.index');
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        return response()->json($this->comparisonService->searchProducts($query, Auth::id()));
    }

    public function history(Request $request): JsonResponse
    {
        $description = $request->input('description', '');

        if (empty($description)) {
            return response()->json([]);
        }

        return response()->json($this->historyService->getTimeline($description, Auth::id()));
    }

    public function byCity(Request $request): JsonResponse
    {
        $productName = $request->input('product', '');

        if ($productName === '') {
            return response()->json([]);
        }

        return response()->json($this->comparisonService->byCity($productName, Auth::id()));
    }

    public function byIssuer(Request $request): JsonResponse
    {
        $productName = $request->input('product', '');
        $city = $request->input('city', '');
        $state = $request->input('state', '');

        if ($productName === '' || $city === '' || $state === '') {
            return response()->json([]);
        }

        return response()->json($this->comparisonService->byIssuer($productName, $city, $state, Auth::id()));
    }
}
