<?php

return [
    /**
     * Control if all the laratrust tables should be truncated before running the seeder.
     */
    'truncate_tables' => true,

    'apps' => [
        'Dashboard' => [
            'management' => ['view'],
        ],
        'Record' => [
            'management' => ['create', 'view', 'modify'],
        ],
        'Nurse' => [
            'management' => ['create', 'view', 'modify'],
        ],
        'Consultant' => [
            'management' => ['create', 'view', 'modify'],
        ],
        'Pharmacy' => [
            'management' => ['create', 'view', 'modify'],
        ],
        'Laboratory' => [
            'management' => ['create', 'view', 'modify'],
        ],
        'Radiology' => [
            'management' => ['create', 'view', 'modify'],
        ],
        'Billing' => [
            'management' => ['create', 'view', 'modify'],
        ],
        'Logs' => [
            'management' => ['view'],
        ],
        'Reports' => [
            'management' => ['view'],
        ],
        'User' => [
            'management' => ['create', 'view', 'modify'],
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
