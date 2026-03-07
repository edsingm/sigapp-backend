<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuração CORS otimizada para integração com Next.js frontend.
    | Permite requisições de subdomínios dinâmicos dos tenants.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),

    'allowed_origins_patterns' => [
        '/https?:\/\/(.+)?\.sigpro\.com\.br/',
        '/https?:\/\/(.+)?\.localhost(:\d+)?/',
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'X-Tenant',
        'X-Tenant-ID',
        'X-Request-ID',
    ],

    'exposed_headers' => [
        'Content-Disposition',
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];
