<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $connection = 'landlord';

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'role_user',
            'role_id',
            'user_id'
        );
    }
}
