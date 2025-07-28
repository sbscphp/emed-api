<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Radiology extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'patient_visit_radiology';
    protected $fillable = [
        'patient_id',
        'admin_id',
        'consultation_id',
        'visitno',
        'lab_dept',
        'test_name',
        'ordered_test',
        'others'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function pharmacist()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function consulted_by()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function consultation()
    {
        return $this->hasOne(Consultation::class, 'consultation_id');
    }
}
