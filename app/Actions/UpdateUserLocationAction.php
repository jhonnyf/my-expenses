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
        $profile = $user->profile;
        $changed = $profile
            ? $profile->cidade !== $cidade || $profile->estado !== $estado
            : $cidade !== null || $estado !== null;

        if (! $changed) {
            return;
        }

        $attributes = ['cidade' => $cidade, 'estado' => $estado];

        // Sem cidade/estado completos as coordenadas antigas deixam de valer (e limpar o campo apaga o dado).
        if (! $cidade || ! $estado) {
            $attributes += ['latitude' => null, 'longitude' => null];
        }

        $profile = $user->profile()->updateOrCreate([], $attributes);

        // updateOrCreate() não atualiza a relação já carregada em cache no model —
        // sem isso, quem já leu $user->profile antes (ex: UserResource) veria o valor antigo.
        $user->setRelation('profile', $profile);

        if ($cidade && $estado) {
            GeocodeUserProfileJob::dispatch($profile->id);
        }
    }
}
