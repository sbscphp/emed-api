<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observetation_Recommandation extends Model
{
    //

    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        "patient_id",
        'patient_name',
        'patient_card_name',
        'date_of_session',
        'time_of_session',
        'counsellor_name',
        'counsellor_id',
        'session_type',
        'means_of_session',
        'schedule_a_follow',
        'schedule_date',
        'referral',
        'details'
    ];
}
