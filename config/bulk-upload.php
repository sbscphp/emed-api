<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Queue Threshold
    |--------------------------------------------------------------------------
    |
    | The number of rows above which the upload will be processed in the background
    | using a queue. If the number of rows is less than this value, the
    | upload will be processed synchronously.
    |
    */
    'queue_threshold' => 50,

    /*
    |--------------------------------------------------------------------------
    | Disk Name
    |--------------------------------------------------------------------------
    |
    | The storage disk to use for storing uploaded files and generated error
    | reports.
    |
    */
    'disk' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Configuration for pruning old bulk upload records and files.
    |
    */
    'prune_after_days' => 1, // Delete files after 24 hours of completion/failure

    /*
    |--------------------------------------------------------------------------
    | Notification Email
    |--------------------------------------------------------------------------
    |
    | Email address to send completion notifications to.
    |
    */
    'notify_email' => 'testresd@yopmail.com',

    /*
    |--------------------------------------------------------------------------
    | Max Upload Size
    |--------------------------------------------------------------------------
    |
    | The maximum file size for bulk uploads in kilobytes.
    | Default is 10240 (10MB).
    |
    */
    'max_upload_size' => 10240,

    /*
    |--------------------------------------------------------------------------
    | Error Export Format
    |--------------------------------------------------------------------------
    |
    | The format for the error sheet (csv or xlsx).
    |
    */
    'error_format' => 'csv',

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | Configuration for multi-tenancy support.
    |
    */
    'multitenancy' => [
        'enabled' => true,
        'resolver' => null, // null = auto-resolve (getTenantId or tenant_id property)
    ],

    'database_connection' => 'landlord',

    /*
    |--------------------------------------------------------------------------
    | Model Mapping
    |--------------------------------------------------------------------------
    |
    | Map aliases to full model classes for cleaner API usage.
    | e.g. 'user' => \App\Models\User::class
    |
    */
    'model_map' => ['medication' => \App\Models\Medication::class],
];
