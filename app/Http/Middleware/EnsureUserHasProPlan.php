<?php

namespace App\Http\Middleware;

use App\Exceptions\ProFeatureRequiredException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasProPlan
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isPro()) {
            throw new ProFeatureRequiredException;
        }

        return $next($request);
    }
}
