<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CaptureUserLocationFromBrowserAction;
use App\Actions\UpdateUserAvatarAction;
use App\Actions\UpdateUserLocationAction;
use App\Http\Requests\CaptureLocationRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\InvoiceItem;
use App\Services\LocationSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function __construct(private readonly LocationSuggestionService $locationSuggestionService) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalInvoices = $user->invoices()->count();

        $totalItems = InvoiceItem::whereHas('invoice', static fn ($q) => $q->where('user_id', $user->id))->count();

        $totalSpent = (float) $user->invoices()->sum('total_amount');
        $memberSince = $user->invoices()->min('issued_at');

        $recentInvoices = $user->invoices()
            ->with('issuer.nicknameForUser')
            ->latest('issued_at')
            ->take(5)
            ->get();

        return $this->success([
            'user' => new UserResource($user),
            'stats' => [
                'total_invoices' => $totalInvoices,
                'total_items' => $totalItems,
                'total_spent' => $totalSpent,
                'member_since' => $memberSince,
            ],
            'recent_invoices' => InvoiceResource::collection($recentInvoices),
            'location_suggestion' => $this->locationSuggestionService->suggestionFor($user),
        ]);
    }

    public function update(UpdateAccountRequest $request, UpdateUserLocationAction $locationAction): JsonResponse
    {
        $user = $request->user();

        $user->fill($request->only('name', 'email'));
        $user->save();

        $locationAction->execute($user, $request->input('cidade'), $request->input('estado'));

        return $this->success(new UserResource($user));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->password = Hash::make($request->validated('password'));
        $user->save();

        return response()->json(['message' => 'Senha alterada com sucesso.']);
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
}
