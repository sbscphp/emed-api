<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = ['patient_id','appointment_date','reason'];
}


