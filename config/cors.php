<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'csrf-cookie',
        'login',
        'logout',
        'user',
        'broadcasting/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => collect(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000', )))
        ->map(fn (string $origin) => rtrim(trim($origin), '/'))
        ->filter()
        ->unique()
        ->values()
        ->all(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Expose auth-related headers when needed
    'exposed_headers' => ['Authorization', 'X-XSRF-TOKEN'],

    'max_age' => 0,

    'supports_credentials' => true,

];
