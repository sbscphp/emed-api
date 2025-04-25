<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laratrust\Models\Role as RoleModel;

class Role extends RoleModel
{
    public $guarded = [];
    protected $connection = 'tenant';
    protected $fillable = ['name', 'display_name', 'description', 'status'];

    /**
     * @property \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id');
    }

    /**
     * @property \Illuminate\Database\Eloquent\Collection|\App\Models\Permission[] $permissions
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role', 'role_id', 'permission_id');
    }
}
