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
        'pharmacy_id',
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
        'drug_id'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function fulfillment()
    {
        return $this->hasOne(TreatmentFulfillment::class);
    }
}
