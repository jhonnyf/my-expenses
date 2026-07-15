<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateUserAvatarAction;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\InvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
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
        ]);
    }

    public function update(UpdateAccountRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->fill($request->only('name', 'email'));
        $user->save();

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
}
