<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consultation_Details_Radiology extends Model
{
    //patient_id patient_visits_id
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'consultation__details__radiologies';

    protected $fillable = [
        'patient_id',
        'patient_visits_id',
        'laborartory_department',
        'laborartory_test',
        'other_laborartory_test',
        'ordered_test'
    ];
}
