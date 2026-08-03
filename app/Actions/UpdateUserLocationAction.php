<?php

namespace App\Actions;

use App\Jobs\GeocodeUserProfileJob;
use App\Models\User;

/**
 * Cria/atualiza cidade/estado do usuário (users_profiles) e dispara o geocoding só
 * quando o valor realmente muda — evita reprocessar o mesmo endereço a cada save.
 * Compartilhada entre cadastro e "Minha Conta", web e API.
 */
class UpdateUserLocationAction
{
    public function execute(User $user, ?string $cidade, ?string $estado): void
    {
        if ($cidade === null && $estado === null) {
            return;
        }

        $profile = $user->profile;
        $changed = ! $profile || $profile->cidade !== $cidade || $profile->estado !== $estado;

        if (! $changed) {
            return;
        }

        $profile = $user->profile()->updateOrCreate([], [
            'cidade' => $cidade,
            'estado' => $estado,
        ]);

        // updateOrCreate() não atualiza a relação já carregada em cache no model —
        // sem isso, quem já leu $user->profile antes (ex: UserResource) veria o valor antigo.
        $user->setRelation('profile', $profile);

        if ($cidade && $estado) {
            GeocodeUserProfileJob::dispatch($profile->id);
        }
    }
}
