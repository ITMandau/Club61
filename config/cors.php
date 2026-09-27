<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Production CORS setting: locked down to specific frontend origins.
    | Mobile clients (Flutter) do not enforce browser CORS.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:3000'),
        // Origin localhost cuma relevan buat dev lokal — jangan pernah ikut ke-load di
        // production (supports_credentials=true di bawah, jadi origin manapun yang lolos
        // di sini otomatis dipercaya kirim cookie/credential).
        ...(env('APP_ENV') === 'production' ? [] : [
            'http://127.0.0.1:3000',
            'http://localhost:8000',
            'http://127.0.0.1:8000',
        ]),
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
