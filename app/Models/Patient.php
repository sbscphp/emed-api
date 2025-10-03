<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];
    protected $connection = 'tenant';

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function nextOfKin()
    {
        return $this->hasOne(NextOfKin::class);
    }

    public function emergencyContact()
    {
        return $this->hasOne(EmergencyContact::class);
    }

    public function visits()
    {
        return $this->hasMany(PatientVisit::class);
    }

    public function visits_recent()
    {
        return $this->hasOne(PatientVisit::class, 'patient_id')->latest();
    }

    public function  patient_visits()
    {
        return $this->hasOne(PatientVisit::class, 'patient_id', 'id');
    }

    public function  patient_visits_latest()
    {
        return $this->hasOne(PatientVisit::class, 'patient_id', 'id')->latest();
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }

    public function triage()
    {
        return $this->hasOne(Triage::class);
    }

    public function medicalHistory()
    {
        return $this->hasMany(MedicalHistory::class);
    }

    public function familyHistory()
    {
        return $this->hasMany(FamilyHistory::class);
    }

    public function socialHistory()
    {
        return $this->hasMany(SocialHistory::class);
    }

    public function billingLogs()
    {
        return $this->hasMany(BillingLog::class, 'patient_id', 'id');
    }

    public function billingLogsForPatient()
    {
        return $this->hasOne(BillingLog::class, 'patient_id', 'id');
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class);
    }


    public function laboratory()
    {
        return $this->hasOne(Laboratory::class, 'patient_id', 'id');
    }


    public function pharmacy()
    {
        return $this->hasOne(Pharmacy::class, 'patient_id', 'id');
    }

    public function radiology()
    {
        return $this->hasOne(Radiology::class, 'patient_id', 'id');
    }
}
