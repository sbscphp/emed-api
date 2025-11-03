<?php

return [
    /**
     * Control if all the laratrust tables should be truncated before running the seeder.
     */
    'truncate_tables' => true,

    'apps' => [
        'dashboard' => [
            'dashboard' => ['view'],
        ],
        'record' => [
            'record' => ['create', 'view', 'modify'],
        ],
        'nurse' => [
            'nurse' => ['create', 'view', 'modify'],
        ],
        'consultant' => [
            'consultant' => ['create', 'view', 'modify'],
        ],
        'pharmacy' => [
            'pharmacy' => ['create', 'view', 'modify'],
        ],
        'laboratory' => [
            'laboratory' => ['create', 'view', 'modify'],
        ],
        'radiology' => [
            'radiology' => ['create', 'view', 'modify'],
        ],
        'billing' => [
            'billing' => ['create', 'view', 'modify'],
        ],
        'logs' => [
            'logs' => ['view'],
        ],
        'reports' => [
            'reports' => ['view'],
        ],
        'user' => [
            'user' => ['create', 'view', 'modify'],
        ],
    ],

    // 'super_admin' => [
    //     'workflow' => [
    //         'view',
    //         'modify',
    //     ],
    //     'vendors' => [
    //         'registrations' => ['view', 'decision'],
    //         'listings' => ['view', 'modify', 'create'],
    //     ],
    //     'marketplace' => [
    //         'products' => ['view', 'decision'],
    //         'transactions' => ['view', 'modify'],
    //     ],
    //     'platform_config' => [
    //         'fees' => ['view', 'modify'],
    //         'subscriptions' => ['view', 'modify', 'subscribers'],
    //         'categories' => ['view', 'modify'],
    //         'content_management' => ['view', 'modify'],
    //         'ads_management' => ['view', 'modify'],
    //         'discounts' => ['view', 'modify'],
    //     ],
    //     'catalogue' => ['view', 'modify'],
    //     'subsidiary' => ['view', 'modify'],
    //     'order_fulfillment' => ['view', 'modify'],
    //     'user_management' => [
    //         'staff' => ['view', 'modify'],
    //         'roles' => ['view', 'modify'],
    //     ],
    //     'audit' => ['view'],
    //     'support' => [
    //         'support' => ['view', 'modify'],
    //         'suggestions' => ['view'],
    //         'returns' => ['view', 'decision'],

    //     ],
    //     'reports' => ['view'],
    // ],

];
