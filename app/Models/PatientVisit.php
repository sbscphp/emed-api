<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientVisit extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'patient_visits';

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function patientBilling()
    {
        return $this->belongsTo(BillingLog::class, 'id', 'visit_id');
    }

    public function consultation()
    {
        return $this->hasOne(Consultation::class, 'visitno');
    }

    public function billingLogs()
    {
        return $this->hasMany(BillingLog::class, 'patient_id', 'patient_id')
            ->where('service_type_id', $this->patient->service_id ?? null);
    }

    public function billingLogsForPatient()
    {
        return $this->hasOne(BillingLog::class, 'visit_id', 'id');
    }
}
