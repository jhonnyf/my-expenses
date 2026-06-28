<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $service) {}

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return $this->success([]);
        }

        return $this->success($this->service->search($query, $request->user()->id));
    }
}
