<?php

namespace App\Http\Controllers\Api\V1;

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
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Jobs\ExportPersonalDataJob;
use App\Services\AccountService;
use App\Services\LocationSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AccountController extends Controller
{
    public function __construct(
        private readonly LocationSuggestionService $locationSuggestionService,
        private readonly AccountService $accountService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'subscription']);

        $recentInvoices = $user->invoices()
            ->with('issuer.nicknameForUser')
            ->latest('issued_at')
            ->take(5)
            ->get();

        return $this->success([
            'user' => new UserResource($user),
            'stats' => $this->accountService->stats($user),
            'recent_invoices' => InvoiceResource::collection($recentInvoices),
            'location_suggestion' => $this->locationSuggestionService->suggestionFor($user),
        ]);
    }

    public function update(UpdateAccountRequest $request, UpdateAccountAction $action, RevokeUserSessionsAction $revokeSessions): JsonResponse
    {
        $user = $request->user();
        $emailChanged = $request->validated('email') !== $user->email;

        $action->execute($user, $request->validated());

        // E-mail é dado de acesso (recuperação de senha): os outros aparelhos saem; o token desta chamada fica.
        if ($emailChanged) {
            $token = $user->currentAccessToken();
            $revokeSessions->execute($user, null, $token instanceof PersonalAccessToken ? $token->id : null);
        }

        return $this->success(new UserResource($user));
    }

    public function updatePassword(UpdatePasswordRequest $request, RevokeUserSessionsAction $revokeSessions): JsonResponse
    {
        $user = $request->user();

        $user->password = Hash::make($request->validated('password'));
        $user->save();

        // Os demais aparelhos precisam entrar de novo; o token que fez esta chamada continua valendo.
        $token = $user->currentAccessToken();
        $revokeSessions->execute($user, null, $token instanceof PersonalAccessToken ? $token->id : null);

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }

    public function revokeOtherSessions(Request $request, RevokeUserSessionsAction $revokeSessions): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        $revokeSessions->execute($user, null, $token instanceof PersonalAccessToken ? $token->id : null);

        return response()->json(['message' => 'Os outros dispositivos foram desconectados.']);
    }

    public function updateAvatar(UpdateAvatarRequest $request, UpdateUserAvatarAction $action): JsonResponse
    {
        $user = $request->user();

        $action->execute($user, $request->file('avatar'));

        return $this->success(new UserResource($user->fresh()));
    }

    public function dismissLocationSuggestion(Request $request): JsonResponse
    {
        $request->user()->profile()->updateOrCreate([], ['location_suggestion_dismissed_at' => now()]);

        return response()->json(['message' => 'Sugestão dispensada.']);
    }

    public function captureLocation(CaptureLocationRequest $request, CaptureUserLocationFromBrowserAction $action): JsonResponse
    {
        $profile = $action->execute(
            $request->user(),
            (float) $request->input('latitude'),
            (float) $request->input('longitude')
        );

        if ($profile === null) {
            return $this->error('Não foi possível identificar sua cidade a partir da localização informada.', 422);
        }

        return $this->success([
            'cidade' => $profile->cidade,
            'estado' => $profile->estado,
            'latitude' => $profile->latitude,
            'longitude' => $profile->longitude,
        ]);
    }

    public function requestExport(Request $request): JsonResponse
    {
        ExportPersonalDataJob::dispatch($request->user()->id);

        return response()->json([
            'message' => 'Estamos preparando seus dados. Você receberá um e-mail com o link para download em instantes.',
        ], 202);
    }

    public function destroy(DeleteAccountRequest $request, DeleteUserAccountAction $action): JsonResponse
    {
        $action->execute($request->user());

        return response()->json(['message' => 'Sua conta foi excluída.']);
    }
}
