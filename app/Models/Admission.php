<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admission extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = ['patient_id','admission_date','discharged_date','status'];
}
