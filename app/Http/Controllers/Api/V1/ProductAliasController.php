<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\ProductNameAiSuggestion;
use App\Http\Requests\AiSuggestProductNameRequest;
use App\Http\Requests\DismissProductAliasSuggestionRequest;
use App\Http\Requests\MergeProductAliasRequest;
use App\Http\Requests\StoreProductAliasRequest;
use App\Services\ProductAliasService;
use App\Services\ProductAliasSuggestionService;
use App\Services\ProductNameAiSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAliasController extends Controller
{
    public function __construct(
        private readonly ProductAliasService $aliasService,
        private readonly ProductAliasSuggestionService $suggestionService,
        private readonly ProductNameAiSuggestionService $aiSuggestionService,
    ) {}

    public function suggestions(Request $request): JsonResponse
    {
        return $this->success($this->suggestionService->suggest($request->user()->id));
    }

    public function suggestionsAll(Request $request): JsonResponse
    {
        return $this->success($this->suggestionService->suggestAll($request->user()->id));
    }

    public function communitySuggestions(Request $request): JsonResponse
    {
        return $this->success($this->aliasService->communitySuggestions($request->user()->id));
    }

    public function aiSuggestName(AiSuggestProductNameRequest $request): JsonResponse
    {
        $descriptions = $request->input('descriptions');
        $shared = $this->aliasService->findSharedCanonicalName($descriptions, $request->user()->id);

        $suggestion = $shared !== null
            ? new ProductNameAiSuggestion(confident: true, suggestedName: $shared)
            : $this->aiSuggestionService->suggestName($descriptions);

        return $this->success([
            ...$suggestion->toArray(),
            'source' => $shared !== null ? 'community' : 'ai',
        ]);
    }

    public function store(StoreProductAliasRequest $request): JsonResponse
    {
        $description = $request->input('description');

        $alias = $this->aliasService->setAlias(
            $request->user()->id,
            $description,
            $request->input('canonical_name')
        );

        return $this->success([
            'description' => $description,
            'canonical_name' => $alias?->canonical_name,
            'display_name' => $alias?->canonical_name ?? $description,
        ]);
    }

    public function merge(MergeProductAliasRequest $request): JsonResponse
    {
        $this->aliasService->mergeInto(
            $request->user()->id,
            $request->input('canonical_name'),
            $request->input('descriptions')
        );

        return $this->success(['success' => true]);
    }

    public function dismiss(DismissProductAliasSuggestionRequest $request): JsonResponse
    {
        $this->suggestionService->dismiss(
            $request->user()->id,
            $request->input('description_a'),
            $request->input('description_b')
        );

        return $this->success(['success' => true]);
    }
}
