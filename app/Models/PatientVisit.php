<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientVisit extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'patient_visits';
    protected $fillable = ['patient_id', 'arrival_date', 'departure_date', 'stage', 'status', 'visitno', 'visit_date'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation()
    {
        return $this->hasOne(Consultation::class, 'visitno');
    }

    public function billingLog()
    {
        return $this->hasOne(BillingLog::class, 'patient_id', 'patient_id')
            ->whereColumn('billing_logs.service_unit_id', 'patient_visits.service_unit_id');
    }
}
