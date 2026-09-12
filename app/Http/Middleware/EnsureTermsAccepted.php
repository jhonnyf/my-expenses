<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasAcceptedCurrentTerms()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'É necessário aceitar os Termos de Uso e a Política de Privacidade atualizados.',
                'terms_acceptance_required' => true,
            ], 403);
        }

        return redirect()->route('terms.accept.form');
    }
}
