<?php

namespace App\Actions;

use App\Exceptions\SocialEmailNotVerifiedException;
use App\Models\User;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class FindOrCreateSocialUser
{
    public function __construct(
        private readonly CreateDefaultCategoriesAction $categoriesAction,
        private readonly RevokeUserSessionsAction $revokeSessions,
    ) {}

    public function handle(SocialiteUser $socialUser, string $provider): User
    {
        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($user) {
            if (is_null($user->email_verified_at)) {
                $user->update(['email_verified_at' => now()]);
            }

            return $user;
        }

        $email = $socialUser->getEmail();

        if ($email) {
            $user = User::where('email', $email)->first();

            if ($user) {
                return $this->linkToExistingUser($user, $socialUser, $provider);
            }
        }

        $user = User::create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
            'email' => $email,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'email_verified_at' => now(),
        ]);

        $this->categoriesAction->execute($user);

        return $user;
    }

    /**
     * Vincula o provedor a uma conta que já existe com esse e-mail — só se o provedor garantiu o e-mail.
     *
     * Conta com e-mail ainda não verificado pode ter sido criada por um terceiro que digitou o e-mail de
     * outra pessoa e escolheu uma senha (pré-sequestro). Ao vincular, essa senha é descartada e os acessos
     * abertos com ela são encerrados; o dono pode definir uma nova senha depois.
     *
     * @throws SocialEmailNotVerifiedException
     */
    private function linkToExistingUser(User $user, SocialiteUser $socialUser, string $provider): User
    {
        if (! $this->providerVerifiesEmail($socialUser)) {
            throw new SocialEmailNotVerifiedException;
        }

        $wasUnverified = $user->email_verified_at === null;

        $user->forceFill([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        if ($wasUnverified) {
            $user->password = null;
        }

        $user->save();

        if ($wasUnverified) {
            $this->revokeSessions->execute($user);
        }

        return $user;
    }

    /**
     * Google e Apple (OIDC) devolvem `email_verified`; a API v2 do Google, `verified_email`. Sem o dado, não vale.
     */
    private function providerVerifiesEmail(SocialiteUser $socialUser): bool
    {
        $raw = $socialUser instanceof AbstractUser ? $socialUser->getRaw() : [];

        return filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
