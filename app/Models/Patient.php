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
    protected $fillable = [
        'firstname',
        'lastname',
        'dob',
        'age',
        'gender',
        'bloodgroup',
        'genotype',
        'email',
        'patient_type',
        'marital_status',
        'phoneno',
        'visitno',
        'occupation',
        'homeaddress',
        'companyaddress',
        'religion',
        'stateoforigin',
        'lga',
        'tribe',
        'cardno',
        'receiptno',
        'status',
        'service_id',
        'arrival_time',
        'depature_time',
        'patientno'
    ];

    public function service()
    {
        return $this->belongsTo(ServiceDepartment::class, 'service_id');
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
        return $this->hasOne(PatientVisit::class, 'visitno', 'visitno')->latest();
    }

    public function  patient_visits()
    {
        return $this->hasOne(PatientVisit::class, 'visitno', 'visitno');
    }
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
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

    public function drugHistory()
    {
        return $this->hasMany(DrugHistory::class);
    }

    public function billingLogs()
    {
        return $this->hasMany(BillingLog::class, 'patient_id', 'patient_id');
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
}
