<?php

namespace App\Http\Middleware;

use App\Exceptions\ProFeatureRequiredException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasProPlan
{
    /** Uso: `pro` ou `pro:<recurso>` (chave de config/plans.php, usada na mensagem e no destaque da página de planos). */
    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        if (! $request->user()?->isPro()) {
            throw new ProFeatureRequiredException($feature);
        }

        return $next($request);
    }
}
