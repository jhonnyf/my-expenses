<?php

namespace App\Http\Controllers;

use App\Actions\CaptureUserLocationFromBrowserAction;
use App\Actions\UpdateUserAvatarAction;
use App\Actions\UpdateUserLocationAction;
use App\Http\Requests\CaptureLocationRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Models\InvoiceItem;
use App\Services\LocationSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private readonly LocationSuggestionService $locationSuggestionService) {}

    public function index(): View
    {
        $user = Auth::user();

        $totalInvoices = $user->invoices()->count();

        $totalItems = InvoiceItem::whereHas('invoice', static function ($query) use ($user): void {
            $query->where('user_id', $user->id);
        })->count();

        $totalSpent = $user->invoices()->sum('total_amount');

        $memberSince = $user->invoices()->min('issued_at');

        $recentInvoices = $user->invoices()
            ->with('issuer.nicknameForUser')
            ->latest('issued_at')
            ->take(5)
            ->get();

        return view('account.index', [
            'user' => $user,
            'totalInvoices' => $totalInvoices,
            'totalItems' => $totalItems,
            'totalSpent' => (float) $totalSpent,
            'memberSince' => $memberSince,
            'recentInvoices' => $recentInvoices,
            'locationSuggestion' => $this->locationSuggestionService->suggestionFor($user),
        ]);
    }

    public function update(UpdateAccountRequest $request, UpdateUserLocationAction $locationAction): RedirectResponse
    {
        $user = Auth::user();

        $user->fill($request->only('name', 'email'));
        $user->save();

        $locationAction->execute($user, $request->input('cidade'), $request->input('estado'));

        return redirect()
            ->route('account.index')
            ->with('success', 'Informações atualizadas com sucesso.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $user->password = Hash::make($request->validated('password'));
        $user->save();

        return redirect()
            ->route('account.index')
            ->with('success_password', 'Senha alterada com sucesso.');
    }

    public function updateAvatar(UpdateAvatarRequest $request, UpdateUserAvatarAction $action): RedirectResponse
    {
        $action->execute(Auth::user(), $request->file('avatar'));

        return redirect()
            ->route('account.index')
            ->with('success_avatar', 'Foto de perfil atualizada com sucesso.');
    }

    public function dismissLocationSuggestion(): RedirectResponse
    {
        Auth::user()->profile()->updateOrCreate([], ['location_suggestion_dismissed_at' => now()]);

        return redirect()->route('account.index');
    }

    public function captureLocation(CaptureLocationRequest $request, CaptureUserLocationFromBrowserAction $action): JsonResponse
    {
        $profile = $action->execute(
            Auth::user(),
            (float) $request->input('latitude'),
            (float) $request->input('longitude')
        );

        if ($profile === null) {
            return response()->json([
                'message' => 'Não foi possível identificar sua cidade a partir da localização informada.',
            ], 422);
        }

        return response()->json([
            'cidade' => $profile->cidade,
            'estado' => $profile->estado,
            'latitude' => $profile->latitude,
            'longitude' => $profile->longitude,
        ]);
    }
}
