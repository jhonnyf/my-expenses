<?php

namespace App\Actions;

use App\Models\User;

/**
 * Nome, e-mail e localização da conta (web e API). Trocar o e-mail invalida a verificação e envia um novo link,
 * já que as rotas autenticadas exigem e-mail verificado.
 */
class UpdateAccountAction
{
    public function __construct(private readonly UpdateUserLocationAction $locationAction) {}

    /**
     * @param  array{name: string, email: string, cidade?: ?string, estado?: ?string}  $data
     */
    public function execute(User $user, array $data): void
    {
        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        // Só mexe na localização quando o cliente a enviou (PATCH parcial); enviar vazio APAGA a localização.
        if (array_key_exists('cidade', $data) || array_key_exists('estado', $data)) {
            $this->locationAction->execute($user, $data['cidade'] ?? null, $data['estado'] ?? null);
        }
    }
}
