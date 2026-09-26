<?php

namespace App\Providers;

use App\Events\InvoiceImported;
use App\Listeners\AutoCategorizeListener;
use App\Listeners\CheckBudgetThresholdsListener;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserProfile;
use App\Observers\InvoiceObserver;
use App\Observers\UserObserver;
use App\Observers\UserProfileObserver;
use Carbon\Carbon;
use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
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
        Event::listen(InvoiceImported::class, CheckBudgetThresholdsListener::class);
        Event::listen(SocialiteWasCalled::class, AppleExtendSocialite::class);

        Invoice::observe(InvoiceObserver::class);
        User::observe(UserObserver::class);
        UserProfile::observe(UserProfileObserver::class);

        $this->configureRateLimiting();
        $this->configureQueryMacros();

        Scramble::routes(fn () => app()->environment('local', 'staging'));

        // O e-mail é enviado de forma síncrona dentro da requisição, então dá pra saber se
        // o pedido veio do app mobile (API) ou do site. O app recebe uma página que abre o
        // deep link cestazen://; o site continua indo direto pra tela de redefinição web.
        $resetUrl = function ($user, $token) {
            $query = ['token' => $token, 'email' => $user->email];

            return request()->is('api/*')
                ? route('password.reset.app', $query)
                : route('password.reset', $query);
        };

        ResetPassword::createUrlUsing($resetUrl);

        ResetPassword::toMailUsing(fn ($user, $token) => (new MailMessage)
            ->subject('Redefina sua senha do CestaZen')
            ->greeting('Olá!')
            ->line('Recebemos um pedido para redefinir a senha da sua conta.')
            ->action('Criar nova senha', $resetUrl($user, $token))
            ->line('Por segurança, este link expira em '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutos.')
            ->line('Se não foi você quem pediu, ignore este e-mail: sua senha continua a mesma.'));
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
