<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureIsSuperAdmin;
use App\Http\Middleware\EnsureTermsAccepted;
use App\Http\Middleware\EnsureUserHasProPlan;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Varre TODAS as rotas registradas: uma rota nova esquecida fora do middleware certo derruba o teste.
 */
class RouteAccessSweepTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rotas que exigem login mas, de propósito, não exigem e-mail verificado / termos aceitos (o usuário
     * precisa alcançá-las justamente para verificar o e-mail, aceitar os termos ou sair).
     */
    private const OPEN_TO_UNVERIFIED = [
        'email/verify', 'email/verify/{id}/{hash}', 'email/verify/resend',
        'aceitar-termos', 'login/logout',
        'api/v1/auth/me', 'api/v1/auth/logout', 'api/v1/auth/email/resend', 'api/v1/terms/accept',
        'api/nfce/upload',
    ];

    /**
     * @return list<Route>
     */
    private function routesWith(string $middlewareClass): array
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => $this->hasMiddleware($route, $middlewareClass))
            ->values()
            ->all();
    }

    private function hasMiddleware(Route $route, string $middlewareClass): bool
    {
        return collect(app('router')->gatherRouteMiddleware($route))
            ->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, $middlewareClass));
    }

    private function hit(Route $route, ?User $user = null)
    {
        $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
        $method = collect($route->methods())->first(fn ($method) => $method !== 'HEAD');
        $request = $user
            ? $this->actingAs($user, str_starts_with($route->uri(), 'api/') ? 'sanctum' : 'web')
            : $this;

        return $request->json($method, $uri);
    }

    private function label(Route $route): string
    {
        return implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri();
    }

    public function test_sweep_finds_a_realistic_number_of_routes(): void
    {
        $this->assertGreaterThan(100, count($this->routesWith(Authenticate::class)));
        $this->assertNotEmpty($this->routesWith(EnsureUserHasProPlan::class));
        $this->assertNotEmpty($this->routesWith(EnsureIsSuperAdmin::class));
    }

    public function test_every_authenticated_route_rejects_guests(): void
    {
        $failures = [];

        foreach ($this->routesWith(Authenticate::class) as $route) {
            $status = $this->hit($route)->getStatusCode();

            if ($status !== 401) {
                $failures[] = $this->label($route)." → {$status}";
            }
        }

        $this->assertSame([], $failures, 'Rotas que não barram visitante sem login');
    }

    public function test_guests_on_the_web_are_sent_to_the_login_page(): void
    {
        $this->get(route('dashboard.index'))->assertRedirect(route('login.index'));
        $this->get(route('verification.notice'))->assertRedirect(route('login.index'));
    }

    public function test_every_authenticated_route_requires_verified_email_and_accepted_terms(): void
    {
        $missing = [];

        foreach ($this->routesWith(Authenticate::class) as $route) {
            if (in_array($route->uri(), self::OPEN_TO_UNVERIFIED, true)) {
                continue;
            }

            foreach ([EnsureEmailIsVerified::class, EnsureTermsAccepted::class] as $required) {
                if (! $this->hasMiddleware($route, $required)) {
                    $missing[] = $this->label($route).' sem '.class_basename($required);
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_verified_and_terms_gates_block_users_on_routes_without_bound_models(): void
    {
        // Os limites de requisição (429) rodam antes dos portões e a varredura repete rotas de propósito.
        $this->withoutMiddleware(ThrottleRequests::class);
        $unverified = User::factory()->unverified()->create();
        $noTerms = User::factory()->create(['terms_accepted_at' => null, 'terms_version' => null]);
        $failures = [];

        foreach ($this->routesWith(EnsureEmailIsVerified::class) as $route) {
            // Com {modelo} o binding (404) roda antes dos middlewares finais; a presença deles já é garantida acima.
            if (str_contains($route->uri(), '{')) {
                continue;
            }

            foreach ([[$unverified, [302, 403, 409]], [$noTerms, [302, 403]]] as [$user, $allowed]) {
                $status = $this->hit($route, $user)->getStatusCode();

                if (! in_array($status, $allowed, true)) {
                    $failures[] = $this->label($route)." → {$status}";
                }
            }
        }

        $this->assertSame([], $failures);
    }

    public function test_every_pro_route_answers_402_to_free_users(): void
    {
        $free = User::factory()->create();
        $failures = [];

        foreach ($this->routesWith(EnsureUserHasProPlan::class) as $route) {
            $response = $this->hit($route, $free);

            if ($response->getStatusCode() !== 402 || $response->json('upgrade_required') !== true) {
                $failures[] = $this->label($route).' → '.$response->getStatusCode();
            }
        }

        $this->assertSame([], $failures);
    }

    public function test_every_admin_route_answers_403_to_regular_users(): void
    {
        $user = User::factory()->pro()->create();
        $failures = [];

        foreach ($this->routesWith(EnsureIsSuperAdmin::class) as $route) {
            if ($this->hit($route, $user)->getStatusCode() !== 403) {
                $failures[] = $this->label($route);
            }
        }

        $this->assertSame([], $failures);
    }
}
