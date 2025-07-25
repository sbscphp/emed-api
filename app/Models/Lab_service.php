<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lab_service extends Model
{

    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        "service_unit_id",
        "class",
        "name",
        "price"
    ];
}
