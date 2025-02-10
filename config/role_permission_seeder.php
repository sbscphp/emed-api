<?php

return [
    /**
     * Control if the seeder should create a user per role while seeding the data.
     */
    'create_users' => false,
    /**
     * Control if all the laratrust tables should be truncated before running the seeder.
     */
    'truncate_tables' => true,

    'roles_structure' => [
        'super_admin' => [
            'category' => 'c,r,u,d',
            'subcategory' => 'c,r,u,d',
            'orders' => 'c,r,u,d',
            'invoices' => 'c,r,u,d',
            'payments' => 'c,r,u,d',
            'configuration' => 'c,r,u,d',
            'services' => 'c,r,u,d',
            'calendar' => 'c,r,u,d',
            'productions' => 'c,r,u,d',
            'products' => 'c,r,u,d',
            'users' => 'c,r,u,d',
            'profile' => 'r,u',
            'global_status' => 'c,r,u,d',
            'hardwares' => 'c,r,u,d',
            'frames' => 'c,r,u,d',
            'logo' => 'c,r,u,d',
            'bank' => 'c,r,u,d',
        ],
        'admin' => [
            'category' => 'c,r,u,d',
            'subcategory' => 'c,r,u,d',
            'orders' => 'c,r,u,d',
            'invoices' => 'c,r,u,d',
            'payments' => 'c,r,u,d',
            'configuration' => 'c,r,u,d',
            'services' => 'c,r,u,d',
            'calendar' => 'c,r,u,d',
            'productions' => 'c,r,u,d',
            'products' => 'c,r,u,d',
            'users' => 'c,r,u,d',
            'profile' => 'r,u',
            'global_status' => 'c,r,u,d',
            'hardwares' => 'c,r,u,d',
            'frames' => 'c,r,u,d',
            'logo' => 'c,r,u,d',
            'bank' => 'c,r,u,d',
        ],
        
        'customer' => [
            'category' => 'c,r,u,d',
            'subcategory' => 'c,r,u,d',
            'orders' => 'c,r,u,d',
            'invoices' => 'c,r,u,d',
            'payments' => 'c,r,u,d',
            'calendar' => 'c,r,u,d',
            'productions' => 'c,r,u,d',
            'products' => 'c,r,u,d',
            'users' => 'c,r,u,d',
            'profile' => 'r,u',
        ],
        'guest' => [
            'category' => 'c,r,u,d',
            'subcategory' => 'c,r,u,d',
            'products' => 'c,r,u,d',
        ],
    ],

    'permissions_map' => [
        'c' => 'create',
        'r' => 'read',
        'u' => 'update',
        'd' => 'delete',
    ]

];
