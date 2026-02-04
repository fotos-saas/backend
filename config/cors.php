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

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'photos/*/preview'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'https://tablostudio.hu'),
        env('FRONTEND_TABLO_URL', 'https://kepvalaszto.hu'),
        // Localhost only in local/development environment
        env('APP_ENV') === 'local' ? 'http://localhost:4200' : null,
        env('APP_ENV') === 'local' ? 'http://localhost:4201' : null,
        env('APP_ENV') === 'local' ? 'http://localhost:4205' : null,
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
