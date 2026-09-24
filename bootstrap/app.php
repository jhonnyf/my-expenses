<?php

use App\Exceptions\AiSuggestionUnavailableException;
use App\Exceptions\ProFeatureRequiredException;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureIsSuperAdmin;
use App\Http\Middleware\EnsureTermsAccepted;
use App\Http\Middleware\EnsureUserHasProPlan;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->alias([
            'auth' => Authenticate::class,
            'pro' => EnsureUserHasProPlan::class,
            'super-admin' => EnsureIsSuperAdmin::class,
            'terms.accepted' => EnsureTermsAccepted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*')
        );

        $exceptions->render(fn (AiSuggestionUnavailableException $e) => response()->json(['message' => $e->getMessage()], 503));

        $exceptions->render(function (ProFeatureRequiredException $e, Request $request) {
            // AJAX da web (axios) também: um redirect para a página de upgrade chegaria como HTML no lugar do JSON esperado.
            if ($request->is('api/*') || $request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'upgrade_required' => true,
                    'feature' => $e->feature(),
                ], 402);
            }

            return redirect()->route('subscription.upgrade')
                ->with('paywall_message', $e->getMessage())
                ->with('paywall_feature', $e->feature());
        });
    })->create();
