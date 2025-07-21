<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingLog extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $fillable = [
        'invoice_number',
        'visit_id',
        'patient_id',
        'patient_name',
        'billing_date',
        'service_type_id',
        'service_unit_id',
        'item_name',
        'unit_price',
        'quantity',
        'payment_status',
        'deposit_amount',
        'payment_method',
        'sub_total',
        'tax_amount',
        'grand_total'
    ];

    public function serviceType()
    {
        return $this->belongsTo(ServiceDepartment::class, 'service_type_id');
    }

    public function serviceUnit()
    {
        return $this->belongsTo(ServiceUnit::class, 'service_unit_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function visits_recent()
    {
        return $this->hasOne(PatientVisit::class, 'visit_id', 'id')->latest();
    }

    public function service()
    {
        return $this->belongsTo(ServiceDepartment::class, 'service_id');
    }
}
