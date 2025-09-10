<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'csrf-cookie',
        'login',
        'logout',
        'user',
    ],

    'allowed_methods' => ['*'],

    // Explicit origins for local Next.js
    'allowed_origins' => [
        'http://localhost:3000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];

