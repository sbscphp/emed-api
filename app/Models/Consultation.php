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

    public function patientVisit()
    {
        return $this->belongsTo(PatientVisit::class, 'visit_id');
    }

    public function labTest()
    {
        return $this->hasMany(Laboratory::class);
    }

    public function radiologyTest()
    {
        return $this->hasMany(Radiology::class);
    }

    public function treatment()
    {
        return $this->hasMany(Treatment::class);
    }

    public function consultedDoctor()
    {
        return $this->belongsTo(User::class, 'consulted_by');
    }
}
