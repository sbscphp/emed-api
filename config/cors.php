<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:5174',
        'https://emed-application.vercel.app',
        'http://127.0.0.1:5500',
        'https://emed.quick-retail.com',
        'https://api.emeddiaries.com',
        'https://emeddiaries.com/',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    // 'exposed_headers' => [],
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 0,
    'supports_credentials' => true,
];
