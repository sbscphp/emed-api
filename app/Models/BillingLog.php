<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingLog extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $connection = 'tenant';

    public function billingLogDetails()
    {
        return $this->hasMany(BillingLogDetail::class, 'billing_id');
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'billing_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_type_id');
    }

    public function serviceUnit()
    {
        return $this->belongsTo(ServiceUnit::class, 'service_unit_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function visits()
    {
        return $this->belongsTo(PatientVisit::class, 'visit_id');
    }

    public function visits_recent()
    {
        return $this->hasOne(PatientVisit::class,  'id', 'visit_id')->latest();
    }
}
