<?php

namespace App\Http\Controllers;

use App\DTOs\ProductNameAiSuggestion;
use App\Http\Requests\AiSuggestProductNameRequest;
use App\Http\Requests\DismissProductAliasSuggestionRequest;
use App\Http\Requests\MergeProductAliasRequest;
use App\Http\Requests\StoreProductAliasRequest;
use App\Services\ProductAliasService;
use App\Services\ProductAliasSuggestionService;
use App\Services\ProductNameAiSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductAliasController extends Controller
{
    public function __construct(
        private readonly ProductAliasService $aliasService,
        private readonly ProductAliasSuggestionService $suggestionService,
        private readonly ProductNameAiSuggestionService $aiSuggestionService,
    ) {}

    public function review(): View
    {
        return view('product-alias.review');
    }

    public function suggestions(): JsonResponse
    {
        return response()->json($this->suggestionService->suggest(Auth::id()));
    }

    public function suggestionsAll(): JsonResponse
    {
        return response()->json($this->suggestionService->suggestAll(Auth::id()));
    }

    public function communitySuggestions(): JsonResponse
    {
        return response()->json($this->aliasService->communitySuggestions(Auth::id()));
    }

    public function aiSuggestName(AiSuggestProductNameRequest $request): JsonResponse
    {
        $descriptions = $request->input('descriptions');
        $shared = $this->aliasService->findSharedCanonicalName($descriptions, Auth::id());

        $suggestion = $shared !== null
            ? new ProductNameAiSuggestion(confident: true, suggestedName: $shared)
            : $this->aiSuggestionService->suggestName($descriptions);

        return response()->json([
            ...$suggestion->toArray(),
            'source' => $shared !== null ? 'community' : 'ai',
        ]);
    }

    public function store(StoreProductAliasRequest $request): JsonResponse
    {
        $description = $request->input('description');

        $alias = $this->aliasService->setAlias(
            Auth::id(),
            $description,
            $request->input('canonical_name')
        );

        return response()->json([
            'description' => $description,
            'canonical_name' => $alias?->canonical_name,
            'display_name' => $alias?->canonical_name ?? $description,
        ]);
    }

    public function merge(MergeProductAliasRequest $request): JsonResponse
    {
        $this->aliasService->mergeInto(
            Auth::id(),
            $request->input('canonical_name'),
            $request->input('descriptions')
        );

        return response()->json(['success' => true]);
    }

    public function dismiss(DismissProductAliasSuggestionRequest $request): JsonResponse
    {
        $this->suggestionService->dismiss(
            Auth::id(),
            $request->input('description_a'),
            $request->input('description_b')
        );

        return response()->json(['success' => true]);
    }
}
