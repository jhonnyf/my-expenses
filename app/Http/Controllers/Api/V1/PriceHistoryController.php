<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PriceQueryRequest;
use App\Services\PriceHistoryService;
use Illuminate\Http\JsonResponse;

class PriceHistoryController extends Controller
{
    private const MIN_SEARCH_LENGTH = 2;

    public function __construct(private readonly PriceHistoryService $service) {}

    public function search(PriceQueryRequest $request): JsonResponse
    {
        $query = $request->searchTerm();

        if (mb_strlen($query) < self::MIN_SEARCH_LENGTH) {
            return $this->success([]);
        }

        return $this->success($this->service->search($query, $request->user()->id));
    }

    public function timeline(PriceQueryRequest $request): JsonResponse
    {
        $description = $request->text('description');

        if ($description === '') {
            return $this->success([]);
        }

        return $this->success($this->service->getTimeline($description, $request->user()->id, $request->unit()));
    }
}
