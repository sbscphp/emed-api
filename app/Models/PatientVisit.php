<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientVisit extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = ['patient_id','arrival_time','departure_time','status','visit_type','visit_date'];
}
