<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListIssuersRequest;
use App\Http\Requests\UpdateIssuerNicknameRequest;
use App\Services\IssuerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class IssuerController extends Controller
{
    public function __construct(private readonly IssuerService $issuers) {}

    public function index(ListIssuersRequest $request): View
    {
        $user = Auth::user();
        $filters = $request->filters();

        return view('issuer.index', [
            'records' => $this->issuers->paginateForUser($user, $filters),
            'favoriteIds' => $user->favoriteIssuers()->pluck('issuers.id'),
            'summary' => $this->issuers->summaryForUser($user),
            'cities' => $this->issuers->citiesForUser($user),
            'filters' => $filters,
            'hasFilters' => $filters['q'] !== '' || $filters['city'] !== '' || $filters['favorites'],
        ]);
    }

    public function detail(Request $request, int $id): View
    {
        $detail = $this->issuers->detailForUser(Auth::user(), $id, $request->query('q'));

        return view('issuer.detail', [
            'record' => $detail['issuer'],
            'invoices' => $detail['invoices'],
            'insights' => $detail['insights'],
            'canComparePrices' => Auth::user()->isPro(),
            'invoiceSearch' => trim((string) $request->query('q')),
            'isFavorite' => $detail['is_favorite'],
            'stats' => $detail['stats'],
            'nickname' => $detail['nickname'],
        ]);
    }

    public function toggleFavorite(int $id): JsonResponse
    {
        return response()->json([
            'is_favorite' => $this->issuers->toggleFavorite(Auth::user(), $id),
        ]);
    }

    public function updateNickname(UpdateIssuerNicknameRequest $request, int $id): JsonResponse
    {
        return response()->json(
            $this->issuers->updateNickname(Auth::user(), $id, $request->input('nickname'))
        );
    }
}
