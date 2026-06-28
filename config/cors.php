<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // Rotas que aceitam requisições cross-origin
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Em produção, substituir '*' pelo domínio/origem do app mobile
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    // Headers necessários para autenticação via Bearer token
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    // false: autenticação via token Bearer, não via cookies de sessão
    'supports_credentials' => false,

];
