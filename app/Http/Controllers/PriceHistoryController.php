<?php

namespace App\Http\Controllers;

use App\Services\PriceHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PriceHistoryController extends Controller
{
    public function __construct(private readonly PriceHistoryService $service) {}

    public function index(): View
    {
        return view('price-history.index');
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        return response()->json($this->service->search($query, Auth::id()));
    }

    public function show(Request $request): JsonResponse
    {
        $description = $request->input('description', '');

        if (empty($description)) {
            return response()->json([]);
        }

        return response()->json($this->service->getTimeline($description, Auth::id()));
    }
}
