<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'patient_visit_treatment';
    protected $fillable = [
        'patient_id',
        'admin_id',
        'consultation_id',
        // 'pharmacy_id',
        'visitno',
        'drug',
        'qualifier',
        'medication',
        'dosage',
        'weight',
        'period',
        'duration',
        'route',
        'remark',
        'receiptno',
        'drug_id',
        'is_surgery',
        'surgery'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function fulfillment()
    {
        return $this->hasOne(TreatmentFulfillment::class);
    }

    public function visit()
    {
        return $this->belongsTo(PatientVisit::class, 'visitno', 'visitno');
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
