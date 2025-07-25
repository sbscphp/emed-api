<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PharmacyService extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        "name",
        "active_ingredent",
        "price",
        "registration_number",
        "service_unit_id"
    ];
}
