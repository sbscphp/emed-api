<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Radiology_lab_patient_examination extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        'radiology_lab_patients_id',
        'examination',
        'result',
        'unit',
        'normal_values',
    ];
}
