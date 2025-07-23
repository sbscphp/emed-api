<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Radiology_lab_patient extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';

    protected $fillable = [
        'patient_id',
        'test_name',
        'user_id'
    ];


    //   'examination',
    //     'result',
    //     'unit',
    //     'normal_values',

    public function doctor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function examinations()
    {
        return $this->hasMany(Radiology_lab_patient_examination::class, 'radiology_lab_patients_id', 'id');
    }
}
