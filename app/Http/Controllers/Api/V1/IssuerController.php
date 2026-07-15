<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\UpdateIssuerNicknameRequest;
use App\Http\Resources\Api\V1\IssuerResource;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssuerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $favoriteIds = $user->favoriteIssuers()->pluck('issuers.id');

        $query = Issuer::query()
            ->select('issuers.*')
            ->leftJoin('issuer_nicknames', function ($join) use ($user) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $user->id);
            })
            ->addSelect('issuer_nicknames.nickname as nickname');

        if ($favoriteIds->isNotEmpty()) {
            $placeholders = implode(',', array_fill(0, $favoriteIds->count(), '?'));
            $query->orderByRaw("FIELD(issuers.id, {$placeholders}) DESC", $favoriteIds->toArray());
        }

        $issuers = $query->orderBy('issuers.name')->paginate(15);

        $issuers->getCollection()->transform(function (Issuer $issuer) use ($favoriteIds) {
            $issuer->is_favorite = $favoriteIds->contains($issuer->id);

            return $issuer;
        });

        return IssuerResource::collection($issuers)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
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
        $issuer->nickname = $issuer->nicknames()->where('user_id', $user->id)->value('nickname');

        return $this->success([
            'issuer' => new IssuerResource($issuer),
            'stats' => $stats,
        ]);
    }

    public function toggleFavorite(Request $request, int $id): JsonResponse
    {
        $issuer = Issuer::findOrFail($id);
        $user = $request->user();

        $result = $user->favoriteIssuers()->toggle($issuer->id);
        $isFavorite = ! empty($result['attached']);

        return $this->success(['is_favorite' => $isFavorite]);
    }

    public function updateNickname(UpdateIssuerNicknameRequest $request, int $id): JsonResponse
    {
        $issuer = Issuer::findOrFail($id);
        $userId = $request->user()->id;
        $nickname = trim((string) $request->input('nickname'));

        if ($nickname === '') {
            $issuer->nicknames()->where('user_id', $userId)->delete();
        } else {
            IssuerNickname::updateOrCreate(
                ['user_id' => $userId, 'issuer_id' => $issuer->id],
                ['nickname' => $nickname]
            );
        }

        return $this->success([
            'nickname' => $nickname !== '' ? $nickname : null,
            'display_name' => $nickname !== '' ? $nickname : $issuer->name,
        ]);
    }
}
