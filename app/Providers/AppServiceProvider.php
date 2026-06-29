<?php

namespace App\Providers;

use App\Events\InvoiceImported;
use App\Listeners\AutoCategorizeListener;
use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(InvoiceImported::class, AutoCategorizeListener::class);
        Event::listen(SocialiteWasCalled::class, AppleExtendSocialite::class);

        $this->configureRateLimiting();

        Scramble::routes(fn () => app()->environment('local', 'staging'));

        ResetPassword::createUrlUsing(
            fn ($user, $token) => url("/reset-password?token={$token}&email=" . urlencode($user->email))
        );
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
