<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PriceHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceHistoryController extends Controller
{
    public function __construct(private readonly PriceHistoryService $service) {}

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return $this->success([]);
        }

        return $this->success($this->service->search($query, $request->user()->id));
    }

    public function timeline(Request $request): JsonResponse
    {
        $description = $request->input('description', '');

        if (empty($description)) {
            return $this->success([]);
        }

        return $this->success($this->service->getTimeline($description, $request->user()->id));
    }
}
