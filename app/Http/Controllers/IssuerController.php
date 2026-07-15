<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateIssuerNicknameRequest;
use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class IssuerController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();
        $favoriteIds = Auth::user()->favoriteIssuers()->pluck('issuers.id');

        $query = Issuer::query()
            ->select('issuers.*')
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            })
            ->addSelect('issuer_nicknames.nickname as nickname')
            ->withCount(['invoices as purchase_count' => fn ($q) => $q->where('user_id', $userId)])
            ->withSum(['invoices as total_spent' => fn ($q) => $q->where('user_id', $userId)], 'total_amount');

        if ($favoriteIds->isNotEmpty()) {
            $placeholders = implode(',', array_fill(0, $favoriteIds->count(), '?'));
            $query->orderByRaw("FIELD(issuers.id, {$placeholders}) DESC", $favoriteIds->toArray());
        }

        $issuers = $query->orderBy('issuers.name')->paginate(15);

        return view('issuer.index', [
            'records' => $issuers,
            'favoriteIds' => $favoriteIds,
            'totalSpent' => (float) Invoice::where('user_id', $userId)->sum('total_amount'),
        ]);
    }

    public function detail(int $id): View
    {
        $user = Auth::user();
        $userId = $user->id;
        $issuer = Issuer::findOrFail($id);

        $issuer->load([
            'invoices' => fn ($q) => $q
                ->where('user_id', $userId)
                ->select(['id', 'issuer_id', 'number', 'series', 'issued_at', 'total_amount'])
                ->withCount('items')
                ->latest('issued_at')
                ->limit(50),
        ]);

        $stats = $issuer->invoices()
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(total_amount), 0) as total_sum, MIN(issued_at) as first_at, MAX(issued_at) as last_at')
            ->first();

        $isFavorite = $user->favoriteIssuers()->where('issuers.id', $id)->exists();
        $nickname = $issuer->nicknames()->where('user_id', $userId)->value('nickname');

        return view('issuer.detail', [
            'record' => $issuer,
            'isFavorite' => $isFavorite,
            'stats' => $stats,
            'nickname' => $nickname,
        ]);
    }

    public function toggleFavorite(int $id): JsonResponse
    {
        $issuer = Issuer::findOrFail($id);
        $user = Auth::user();

        $result = $user->favoriteIssuers()->toggle($issuer->id);
        $isFavorite = ! empty($result['attached']);

        return response()->json(['is_favorite' => $isFavorite]);
    }

    public function updateNickname(UpdateIssuerNicknameRequest $request, int $id): JsonResponse
    {
        $issuer = Issuer::findOrFail($id);
        $userId = Auth::id();
        $nickname = trim((string) $request->input('nickname'));

        if ($nickname === '') {
            $issuer->nicknames()->where('user_id', $userId)->delete();
        } else {
            IssuerNickname::updateOrCreate(
                ['user_id' => $userId, 'issuer_id' => $issuer->id],
                ['nickname' => $nickname]
            );
        }

        return response()->json([
            'nickname' => $nickname !== '' ? $nickname : null,
            'display_name' => $nickname !== '' ? $nickname : $issuer->name,
        ]);
    }
}
