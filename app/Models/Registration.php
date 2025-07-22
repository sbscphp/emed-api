<?php

namespace App\Models;

use Spatie\Multitenancy\Models\Tenant as BaseTenant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class Registration extends BaseTenant
{
    protected $fillable = [
        'name',
        'state_city',
        'registration_number',
        'email',
        'phone_number',
        'address',
        'license',
        'theme_color',
        'logo',
        'updated_by',
    ];

    public static function booted()
    {
        static::creating(function ($tenant) {
            $tenant->database = 'tenant_' . Str::slug($tenant->name, '_');
            if (empty($tenant->domain)) {
                $tenant->domain = Str::slug($tenant->name, '-') . '.emed.com';
                // $tenant->domain = Str::slug($tenant->name, '-') . '.hospitalapp.com';
            }
        });

        static::updating(function ($tenant) {
            $tenant->updated_by = Auth::check() ? Auth::id() : null;
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'domain', 'domain');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
