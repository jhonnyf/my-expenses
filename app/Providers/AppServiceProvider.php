<?php

namespace App\Providers;

use App\Events\InvoiceImported;
use App\Listeners\AutoCategorizeListener;
use Carbon\Carbon;
use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
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
        $this->configureQueryMacros();

        Scramble::routes(fn () => app()->environment('local', 'staging'));

        ResetPassword::createUrlUsing(
            fn ($user, $token) => url("/reset-password?token={$token}&email=".urlencode($user->email))
        );
    }

    /**
     * whereDate($col, '>=', $a)->whereDate($col, '<=', $b) envolve a coluna em DATE(...),
     * o que impede o MySQL de usar índices sobre ela (ex: invoices_user_id_issued_at_index) —
     * confirmado via EXPLAIN. Comparar o valor bruto contra os limites do dia evita isso.
     */
    private function configureQueryMacros(): void
    {
        Builder::macro('whereDateBetween', function (string $column, string $start, string $end) {
            /** @var Builder $this */
            return $this->where($column, '>=', $start)
                ->where($column, '<', Carbon::parse($end)->addDay()->format('Y-m-d'));
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('ai-suggestions', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        // Nominatim (OpenStreetMap) exige no máximo 1 requisição/segundo por política de uso.
        RateLimiter::for('geocoding', fn () => Limit::perSecond(1));
    }
}
