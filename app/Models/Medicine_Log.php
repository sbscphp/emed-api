<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine_Log extends Model
{
    protected $table = 'medicine__logs';
    protected $connection = 'tenant';
    protected $fillable = [
        'patient_id',
        'medication_id',
        'pharmacy_id',
        'presscribed_drug',
        'patient_status',
        'status',
        'action',
        'visitno',
        'arrival_date'
    ];


    public function patient()
    {
        return $this->belongsTo(Patient::class, "patient_id");
    }

    public function medication()
    {
        return $this->belongsTo(Medication::class, "medication_id");
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class, 'pharmacy_id');
    }
}
