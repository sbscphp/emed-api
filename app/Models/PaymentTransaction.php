<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $connection = 'tenant';

    protected $casts = [
        'metadata' => 'array',
        'paid_at'  => 'datetime',
        'amount'   => 'decimal:2',
    ];

    public function billingLog()
    {
        return $this->belongsTo(BillingLog::class, 'billing_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
