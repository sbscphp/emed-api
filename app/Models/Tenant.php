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

    // public static function booted()
    // {
    //     static::creating(function ($tenant) {
    //         $tenant->database = 'tenant_' . Str::slug($tenant->name, '_');
    //     });
    // }
    public static function booted()
    {
        static::creating(function ($tenant) {
            if (!app()->environment('production')) {
                // $tenant->database = 'jkpmjemy_tenant_' . Str::slug($tenant->name, '_') . '_' . Str::random(4);
                $tenant->database = 'tenant_' . Str::slug($tenant->name, '_');
            } elseif (empty($tenant->database)) {
                $tenant->database = 'jkpmjemy_tenant_john_hospital';
            }
        });
    }

    public function register()
    {
        return $this->hasMany(Registration::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
