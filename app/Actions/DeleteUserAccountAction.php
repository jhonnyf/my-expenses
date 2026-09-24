<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUserAccountAction
{
    public function __construct(private readonly RevokeUserSessionsAction $revokeSessions) {}

    /**
     * `invoices.user_id`/`qrcode_reads.user_id` usam nullOnDelete no schema —
     * ao apagar o usuário, essas notas ficam anonimizadas (não removidas),
     * preservando o histórico agregado de preços. Os demais dados exclusivos
     * do usuário (perfil, assinatura, categorias, listas etc.) usam
     * cascadeOnDelete e somem junto.
     */
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            // ->each->delete() (não ->files()->delete()) para disparar o hook
            // de model File::booted() que remove o arquivo físico do disco.
            $user->files()->get()->each->delete();

            // notifications é polimórfica (sem cascade) e pode ter dado pessoal; sessões e tokens não valem mais.
            $user->notifications()->delete();
            $this->revokeSessions->execute($user);

            $user->delete();
        });
    }
}
