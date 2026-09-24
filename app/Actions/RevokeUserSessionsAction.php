<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Encerra os acessos do usuário: sessões web (driver `database`), cookies "lembrar-me" e tokens Sanctum (app).
 * Usada ao trocar a senha, no "sair dos outros dispositivos" e na exclusão da conta.
 */
class RevokeUserSessionsAction
{
    public function execute(User $user, ?string $exceptSessionId = null, ?int $exceptTokenId = null): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
                ->delete();
        }

        $user->tokens()
            ->when($exceptTokenId !== null, fn ($query) => $query->where('id', '!=', $exceptTokenId))
            ->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
