<?php

namespace App\Http\Controllers;

use App\Actions\CaptureUserLocationFromBrowserAction;
use App\Actions\DeleteUserAccountAction;
use App\Actions\RevokeUserSessionsAction;
use App\Actions\UpdateAccountAction;
use App\Actions\UpdateUserAvatarAction;
use App\Http\Requests\CaptureLocationRequest;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Jobs\ExportPersonalDataJob;
use App\Models\File;
use App\Models\User;
use App\Services\AccountService;
use App\Services\LocationSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function __construct(
        private readonly LocationSuggestionService $locationSuggestionService,
        private readonly AccountService $accountService,
    ) {}

    public function index(): View
    {
        $user = Auth::user()->load(['profile', 'subscription']);

        return view('account.index', [
            'user' => $user,
            'stats' => $this->accountService->stats($user),
            'locationSuggestion' => $this->locationSuggestionService->suggestionFor($user),
        ]);
    }

    public function update(UpdateAccountRequest $request, UpdateAccountAction $action): RedirectResponse
    {
        $user = Auth::user();
        $emailChanged = $request->validated('email') !== $user->email;

        $action->execute($user, $request->validated());

        return redirect()
            ->route('account.index', ['tab' => 'settings'])
            ->with('success', $emailChanged
                ? 'Informações atualizadas. Enviamos um link para confirmar o novo e-mail.'
                : 'Informações atualizadas com sucesso.');
    }

    public function updatePassword(UpdatePasswordRequest $request, RevokeUserSessionsAction $revokeSessions): RedirectResponse
    {
        $user = Auth::user();

        $user->password = Hash::make($request->validated('password'));
        $user->save();

        // Quem tinha a senha antiga (outros navegadores, o app) precisa entrar de novo; esta sessão continua.
        $revokeSessions->execute($user, $request->session()->getId());

        return redirect()
            ->route('account.index', ['tab' => 'security'])
            ->with('success', 'Senha alterada com sucesso. Os outros dispositivos foram desconectados.');
    }

    public function revokeOtherSessions(Request $request, RevokeUserSessionsAction $revokeSessions): RedirectResponse
    {
        $revokeSessions->execute(Auth::user(), $request->session()->getId());

        return redirect()
            ->route('account.index', ['tab' => 'security'])
            ->with('success', 'Os outros dispositivos foram desconectados.');
    }

    public function updateAvatar(UpdateAvatarRequest $request, UpdateUserAvatarAction $action): RedirectResponse
    {
        $action->execute(Auth::user(), $request->file('avatar'));

        return redirect()
            ->route('account.index', ['tab' => 'settings'])
            ->with('success', 'Foto de perfil atualizada com sucesso.');
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

    public function requestExport(): RedirectResponse
    {
        ExportPersonalDataJob::dispatch(Auth::id());

        return redirect()
            ->route('account.index', ['tab' => 'security'])
            ->with('success', 'Estamos preparando seus dados. Você receberá um e-mail com o link para download em instantes.');
    }

    public function downloadExport(File $file): StreamedResponse
    {
        abort_if($file->collection !== 'personal-data-export', 404);
        abort_if($file->fileable_type !== User::class || $file->fileable_id !== Auth::id(), 403);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function destroy(DeleteAccountRequest $request, DeleteUserAccountAction $action): RedirectResponse
    {
        $user = Auth::user();

        // Logout precisa vir antes da exclusão: se ocorrer depois, o guard tenta
        // gravar um novo "remember token" no model já deletado, o que faz o
        // Eloquent reinserir a linha (exists=false -> save() vira INSERT).
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $action->execute($user);

        return redirect()->route('login.index')->with('success', 'Sua conta foi excluída.');
    }
}
