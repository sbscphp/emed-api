<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'patient_visit_consultation';
    protected $fillable = [
        'patient_id',
        'admin_id',
        'visitno',
        'complaint',
        'complaint_history',
        'review',
        'diagnosis',
        'allergy',
        'disease_pattern',
        'disease_type',
        'investigation',
        'follow_up',
        'followUp_date',
        'referral',
        'referral_detail',
        'admitted'
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

    public function patientVisit()
    {
        return $this->belongsTo(PatientVisit::class, 'visitno', 'visitno');
    }

    public function pharmacist()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
