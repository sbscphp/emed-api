<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dosage_Adminstration extends Model
{
    protected $connection = 'tenant';
    protected $fillable = [
        "vaccine_name",
        "vaccine_code",
        "dosage",
        "weight",
        "batch_number",
        "administration_date",
        "manufacturer",
        "expiration_date",
        "route_of_adminstration",
        "injection_site",
        "administration_date",
        "manufacturer",
        "expiration_date",
        "route_of_administration",
        "injection_site",
        "administering_health_professional"
    ];
}
