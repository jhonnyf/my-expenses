<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ListIssuersRequest;
use App\Http\Requests\UpdateIssuerNicknameRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\IssuerResource;
use App\Models\Issuer;
use App\Services\IssuerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssuerController extends Controller
{
    public function __construct(private readonly IssuerService $issuers) {}

    public function index(ListIssuersRequest $request): JsonResponse
    {
        $user = $request->user();
        $favoriteIds = $user->favoriteIssuers()->pluck('issuers.id');

        $issuers = $this->issuers->paginateForUser($user, $request->filters());

        $issuers->getCollection()->transform(function (Issuer $issuer) use ($favoriteIds) {
            $issuer->is_favorite = $favoriteIds->contains($issuer->id);

            return $issuer;
        });

        return IssuerResource::collection($issuers)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $detail = $this->issuers->detailForUser($request->user(), $id, $request->query('q'));

        $issuer = $detail['issuer'];
        $issuer->is_favorite = $detail['is_favorite'];
        $issuer->nickname = $detail['nickname'];

        return $this->success([
            'issuer' => new IssuerResource($issuer),
            'stats' => $detail['stats'],
            'insights' => $detail['insights'],
            'invoices' => InvoiceResource::collection($detail['invoices']->items()),
            'invoices_meta' => [
                'current_page' => $detail['invoices']->currentPage(),
                'last_page' => $detail['invoices']->lastPage(),
                'per_page' => $detail['invoices']->perPage(),
                'total' => $detail['invoices']->total(),
            ],
        ]);
    }

    public function toggleFavorite(Request $request, int $id): JsonResponse
    {
        return $this->success([
            'is_favorite' => $this->issuers->toggleFavorite($request->user(), $id),
        ]);
    }

    public function updateNickname(UpdateIssuerNicknameRequest $request, int $id): JsonResponse
    {
        return $this->success(
            $this->issuers->updateNickname($request->user(), $id, $request->input('nickname'))
        );
    }
}
