<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laratrust\Traits\HasRolesAndPermissions;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\Tenant;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    /**
     * @method bool hasRole(string|array $roles)
     */
    use  HasRolesAndPermissions, HasFactory, Notifiable, HasApiTokens, SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];
    protected $connection = 'landlord';
    // protected $appends = ['role_names'];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function userInformation()
    {
        return $this->hasOne(UserInformation::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user', 'user_id', 'permission_id');
    }


    public function getRoleNamesAttribute()
    {
        return $this->roles->pluck('name');
    }

    // User.php
    // public function tenants()
    // {
    //     return $this->belongsToMany(Tenant::class, 'tenant_user')
    //         ->withPivot(['profile_picture', 'status'])
    //         ->withTimestamps();
    // }

    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users');
    }

    public function currentTenant()
    {
        $currentTenant = Tenant::current();

        return $this->tenants()->where('tenant_id', $currentTenant?->id);
    }

    public function getCurrentTenant()
    {
        $currentTenant = Tenant::current();

        return $this->tenants()
            ->where('tenant_id', $currentTenant?->id)
            ->first();
    }

    public function tenantContext($tenantId)
    {
        return $this->hasOne(TenantUser::class, 'user_id')
            ->where('tenant_id', $tenantId);
    }

    public function tenantUsers()
    {
        return $this->hasMany(TenantUser::class);
    }

    public function superAdminRoles()
    {
        return $this->belongsToMany(SuperAdminRole::class, 'super_admin_role_user', 'user_id', 'super_admin_role_id');
    }
}
