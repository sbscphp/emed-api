<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'patient_visit_consultation';
    protected $casts = [
        'complaints' => 'array',
        'allergies' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function lab()
    {
        return $this->hasMany(Laboratory::class);
    }

    public function treatment()
    {
        return $this->hasMany(Treatment::class);
    }

    // public function patientVisit()
    // {
    //     return $this->belongsTo(PatientVisit::class, 'visitno', 'visitno');
    // }

    public function patientVisit()
    {
        return $this->belongsTo(PatientVisit::class, 'visitno', 'visitno');
    }
    public function pharmacist()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
