<?php

namespace App\Models;

use Spatie\Multitenancy\Models\Tenant as BaseTenant;
use Illuminate\Support\Str;

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
            $tenant->database = 'tenant_' . Str::slug($tenant->name, '_');
        });
    }
    

}