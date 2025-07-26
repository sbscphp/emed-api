<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consultation_Details_Laborartory extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        'patient_id',
        'patient_visits_id',
        'laborartory_dept',
        'laborartory_test',
        'other_laborartory',
        'order_test'
    ];
}
