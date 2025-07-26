<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registartion_Service extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        "service_unit_id",
        "name",
        "price"
    ];
}
