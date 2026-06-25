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

        $issuers = Issuer::orderByRaw('FIELD(id, '.($favoriteIds->isNotEmpty() ? $favoriteIds->implode(',') : '0').') DESC')
            ->orderBy('name')
            ->paginate(15);

        return view('issuer.index', [
            'records' => $issuers,
            'favoriteIds' => $favoriteIds,
        ]);
    }

    public function detail(int $id): View
    {
        $userId = Auth::id();
        $issuer = Issuer::findOrFail($id);

        $issuer->load([
            'invoices' => fn ($q) => $q->where('user_id', $userId)->latest('issued_at')->limit(50),
            'invoices.items',
        ]);

        $isFavorite = Auth::user()->favoriteIssuers()->where('issuers.id', $id)->exists();

        return view('issuer.detail', [
            'record' => $issuer,
            'isFavorite' => $isFavorite,
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
