<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceDepartment extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'services';
    protected $fillable = [
        'name',
    ];

    public function patients()
    {
        return $this->hasOne(Patient::class, "service_id", 'id');
    }
}
