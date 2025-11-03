<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Treatment extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'patient_visit_treatment';

    public function visit()
    {
        return $this->belongsTo(PatientVisit::class, 'visit_id', 'id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function pharmacyRequest()
    {
        return $this->belongsTo(PharmacyRequest::class, 'drug_id', 'id');
    }

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

    public function billingLogDetail()
    {
        return $this->belongsTo(BillingLogDetail::class, 'id', 'treatment_id');
    }

    public function dispensedUser()
    {
        return $this->belongsTo(User::class, 'dispensed_by', 'id');
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }
    
    public function billingLogs()
    {
        return $this->hasOneThrough(
            BillingLog::class,         // Final model
            PatientVisit::class,       // Intermediate model
            'visitno',                 // Foreign key on PatientVisit for PatientVisitLab (referenced by 'visitno')
            'visit_id',                // Foreign key on BillingLog pointing to PatientVisit (visit_id = id)
            'visitno',                 // Local key on PatientVisitLab
            'id'                       // Local key on PatientVisit
        );
    }
}
