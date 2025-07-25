<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consultation_service extends Model
{
    //

    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        "service_unit_id",
        "name",
        "price"
    ];
}
