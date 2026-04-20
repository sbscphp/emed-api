<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuperAdminRole extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'landlord';
    protected $guarded = ['id'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'super_admin_role_user', 'super_admin_role_id', 'user_id');
    }
}
