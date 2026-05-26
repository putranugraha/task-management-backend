<?php

return [
    'mail_enabled' => env('NOTIFICATIONS_MAIL_ENABLED', false),
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:3000')),
];
