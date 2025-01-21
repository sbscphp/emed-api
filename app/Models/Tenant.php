<?php

namespace App\Models;

use Spatie\Multitenancy\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    protected $fillable = [
        'name',
        'domain',
        'database',
        'created_by',
        'updated_by'
    ];

    public static function booted()
    {
        static::creating(function ($tenant) {
            $tenant->database = 'tenant_' . strtolower($tenant->name);
        });
    }
    

}