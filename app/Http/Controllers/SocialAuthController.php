<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateSocialUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SocialAuthController extends Controller
{
    private const ALLOWED_PROVIDERS = ['google', 'facebook', 'apple'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureProviderAllowed($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider, FindOrCreateSocialUser $action): RedirectResponse
    {
        $this->ensureProviderAllowed($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
            $user = $action->handle($socialUser, $provider);

            Auth::login($user, remember: true);

            return redirect()->intended(route('dashboard.index'));
        } catch (\Throwable $e) {
            Log::error("Social login error [{$provider}]: " . $e->getMessage());

            return redirect()->route('login.index')->withErrors([
                'email' => __('auth.social_failed'),
            ]);
        }
    }

    private function ensureProviderAllowed(string $provider): void
    {
        if (! in_array($provider, self::ALLOWED_PROVIDERS, strict: true)) {
            throw new NotFoundHttpException();
        }
    }
}
