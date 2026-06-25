<?php

namespace App\Http\Controllers;

use App\Models\Issuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class IssuerController extends Controller
{
    public function index(): View
    {
        $favoriteIds = Auth::user()->favoriteIssuers()->pluck('issuers.id');

        $query = Issuer::query();

        if ($favoriteIds->isNotEmpty()) {
            $placeholders = implode(',', array_fill(0, $favoriteIds->count(), '?'));
            $query->orderByRaw("FIELD(id, {$placeholders}) DESC", $favoriteIds->toArray());
        }

        $issuers = $query->orderBy('name')->paginate(15);

        return view('issuer.index', [
            'records'     => $issuers,
            'favoriteIds' => $favoriteIds,
        ]);
    }

    public function detail(int $id): View
    {
        $user   = Auth::user();
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

        return view('issuer.detail', [
            'record'     => $issuer,
            'isFavorite' => $isFavorite,
            'stats'      => $stats,
        ]);
    }

    public function toggleFavorite(int $id): JsonResponse
    {
        $issuer = Issuer::findOrFail($id);
        $user = Auth::user();

        $result = $user->favoriteIssuers()->toggle($issuer->id);
        $isFavorite = !empty($result['attached']);

        return response()->json(['is_favorite' => $isFavorite]);
    }
}
