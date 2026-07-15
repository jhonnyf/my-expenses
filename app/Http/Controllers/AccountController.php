<?php

namespace App\Http\Controllers;

use App\Actions\UpdateUserAvatarAction;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Models\InvoiceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountController extends Controller
{
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
        ]);
    }

    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $user->fill($request->only('name', 'email'));
        $user->save();

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
}
