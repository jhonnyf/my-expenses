<?php

namespace App\Http\Controllers;

use App\Models\Issuer;
use Illuminate\Support\Facades\Auth;

class IssuerController extends Controller
{
    public function index()
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

    public function detail($id)
    {
        $userId = Auth::id();
        $issuer = Issuer::with(['invoices' => fn ($q) => $q->where('user_id', $userId), 'invoices.items'])->findOrFail($id);
        $isFavorite = Auth::user()->favoriteIssuers()->where('issuers.id', $id)->exists();

        return view('issuer.detail', [
            'record' => $issuer,
            'isFavorite' => $isFavorite,
        ]);
    }

    public function toggleFavorite($id)
    {
        $issuer = Issuer::findOrFail($id);
        $user = Auth::user();

        $result = $user->favoriteIssuers()->toggle($issuer->id);
        $isFavorite = ! empty($result['attached']);

        return response()->json(['is_favorite' => $isFavorite]);
    }
}
