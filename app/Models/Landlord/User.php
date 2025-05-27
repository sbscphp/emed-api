<?php

namespace App\Models\Landlord;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Laratrust\Traits\HasRolesAndPermissions;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    /**
     * @method bool hasRole(string|array $roles)
     */
    use  HasRolesAndPermissions, HasFactory, Notifiable, HasApiTokens;
    protected $connection = 'landlord';
    protected $table = 'users';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'tenant_id',
        'uuid',
        'fullname',
        'email',
        'role',
        'phone_number',
        'date_of_birth',
        'email_verified_at',
        'can_login',
        'is_verified',
        'is_active',
        'password',
    ];

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_user',
            'user_id',
            'role_id'
        );
    }
}
