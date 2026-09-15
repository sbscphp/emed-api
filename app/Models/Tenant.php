<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use SoftDeletes;

    protected $guarded = ['id'];

    // public static function booted()
    // {
    //     static::creating(function ($tenant) {
    //         $tenant->database = 'tenant_' . Str::slug($tenant->name, '_');
    //     });
    // }
    public static function booted()
    {
        // static::creating(function ($tenant) {
        //     if (!app()->environment('production')) {
        //         // $tenant->database = 'jkpmjemy_tenant_' . Str::slug($tenant->name, '_') . '_' . Str::random(4);
        //         $tenant->database = 'tenant_' . Str::slug($tenant->name, '_');
        //     } elseif (empty($tenant->database)) {
        //         $tenant->database = 'jkpmjemy_tenant_john_hospital';
        //     }
        // });
    }

    // public function users()
    // {
    //     return $this->hasMany(User::class);
    // }

    // public function users()
    // {
    //     return $this->belongsToMany(User::class, 'tenant_user')
    //         ->withPivot(['profile_picture', 'status'])
    //         ->withTimestamps();
    // }

    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_users');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'tenant_id');
    }

    public function usageCharges()
    {
        return $this->hasMany(ClientUsageCharge::class, 'tenant_id');
    }

    /**
     * The workspace's own palette. One row per tenant — see App\Models\Landlord\Theme.
     */
    public function theme(): HasOne
    {
        return $this->hasOne(Theme::class);
    }

    /**
     * The tenant's theme as a client/email-friendly array, or null when no
     * theme is set. Single source of truth for the theme payload.
     */
    public function themeData(): ?array
    {
        $this->loadMissing('theme');

        if (! $this->theme) {
            return null;
        }

        return [
            'id' => $this->theme->id,
            'name' => $this->theme->name,
            'primary_color' => $this->theme->primary_color,
            'secondary_color' => $this->theme->secondary_color,
            'tertiary_color' => $this->theme->tertiary_color,
        ];
    }
}
