<?php

namespace App\Actions;

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class FindOrCreateSocialUser
{
    public function __construct(private readonly CreateDefaultCategoriesAction $categoriesAction) {}

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
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);

                return $user;
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
}
