<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentFulfillment extends Model
{
    protected $guarded = [];
    protected $connection = 'tenant';
    protected $fillable = [
        'treatment_id',
        'dispensing_pharmacist',
        'dispensing_date',
        'quantity_dispensed',
        'batch_number',
        'expiry_date',
        'prescription_status',
        'payment_status',
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }
}
