<?php

namespace App\Models\Landlord;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $connection = 'landlord';

    protected $table = 'users';

    protected $fillable = [
        'tenant_id',
        'uuid',
        'fullname',
        'email',
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
