<?php

$defaultAllowedOrigins = [
    'http://localhost:5173',
    'https://timly-hris.netlify.app',
];

$envAllowedOrigins = array_filter(array_map(
    'trim',
    explode(',', env('CORS_ALLOWED_ORIGINS', ''))
));

$allowedOrigins = array_values(array_unique(array_merge(
    $defaultAllowedOrigins,
    $envAllowedOrigins
)));

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    // Batasi hanya method HTTP yang benar-benar digunakan aplikasi.
    // Jangan gunakan ['*'] di production karena mengizinkan TRACE, CONNECT, dll.
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Additional production origins can be configured in .env:
    //   CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://app.yourdomain.com
    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Company-Id',
        'Accept',
        'Origin',
    ],

    'exposed_headers' => [],

    'max_age' => 86400, // 24 jam preflight cache — mengurangi jumlah OPTIONS request

    'supports_credentials' => false,
];
