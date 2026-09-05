<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://emr.enuguinternationalhospital.com',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'https://emed-application.vercel.app',
        'http://127.0.0.1:5500',
        'https://emed.quick-retail.com',
        'https://api.emeddiaries.com',
        'https://emeddiaries.com',
        'https://emed-superadmin.vercel.app',
        'https://admin.emeddiaries.com',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    // 'exposed_headers' => [],
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 0,
    'supports_credentials' => true,
];
