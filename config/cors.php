<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        'https://demo-restaurant-smoky.vercel.app/login,http://localhost:5173,http://127.0.0.1:5173,http://localhost,http://127.0.0.1'
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Con Bearer token no es obligatorio, pero dejamos configurable.
    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', false),

];
