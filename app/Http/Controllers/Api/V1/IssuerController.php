<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\IssuerResource;
use App\Models\Issuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssuerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $favoriteIds = $user->favoriteIssuers()->pluck('issuers.id');

        $query = Issuer::query();

        if ($favoriteIds->isNotEmpty()) {
            $placeholders = implode(',', array_fill(0, $favoriteIds->count(), '?'));
            $query->orderByRaw("FIELD(id, {$placeholders}) DESC", $favoriteIds->toArray());
        }

        $issuers = $query->orderBy('name')->paginate(15);

        $issuers->getCollection()->transform(function (Issuer $issuer) use ($favoriteIds) {
            $issuer->is_favorite = $favoriteIds->contains($issuer->id);
            return $issuer;
        });

        return IssuerResource::collection($issuers)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $issuer = Issuer::findOrFail($id);

        $issuer->load([
            'invoices' => fn ($q) => $q
                ->where('user_id', $user->id)
                ->select(['id', 'issuer_id', 'number', 'series', 'issued_at', 'total_amount'])
                ->withCount('items')
                ->latest('issued_at')
                ->limit(50),
        ]);

        $stats = $issuer->invoices()
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(total_amount), 0) as total_sum, MIN(issued_at) as first_at, MAX(issued_at) as last_at')
            ->first();

        $issuer->is_favorite = $user->favoriteIssuers()->where('issuers.id', $id)->exists();

        return $this->success([
            'issuer' => new IssuerResource($issuer),
            'stats'  => $stats,
        ]);
    }

    public function toggleFavorite(Request $request, int $id): JsonResponse
    {
        $issuer = Issuer::findOrFail($id);
        $user   = $request->user();

        $result     = $user->favoriteIssuers()->toggle($issuer->id);
        $isFavorite = !empty($result['attached']);

        return $this->success(['is_favorite' => $isFavorite]);
    }
}
