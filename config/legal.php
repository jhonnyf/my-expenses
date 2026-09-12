<?php

return [
    /*
     * Bump ao publicar mudanças relevantes nos Termos de Uso ou na Política
     * de Privacidade — força o re-consentimento de quem já aceitou uma
     * versão anterior (ver App\Http\Middleware\EnsureTermsAccepted).
     */
    'current_terms_version' => env('LEGAL_TERMS_VERSION', '2026-09-11'),
];
