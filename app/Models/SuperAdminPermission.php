<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperAdminPermission extends Model
{
    protected $connection = 'landlord';
    protected $guarded = ['id'];

    public function roles()
    {
        return $this->belongsToMany(SuperAdminRole::class, 'super_admin_permission_role', 'super_admin_permission_id', 'super_admin_role_id');
    }
}
