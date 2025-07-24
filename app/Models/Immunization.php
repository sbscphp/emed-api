<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Immunization extends Model
{
    protected $connection = 'tenant';
    protected $fillable = [
        "immunization_type",
        "schedule_a_follow_up",
        "schedule_a_follow_up_date",
        "referral",
        "referral_detail",
        "patient_id"
    ];
}
