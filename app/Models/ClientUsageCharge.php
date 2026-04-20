<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientUsageCharge extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'landlord';
    protected $table = 'client_usage_charges';

    protected $casts = [
        'billing_month'  => 'date',
        'fee_per_visit'  => 'decimal:2',
        'total_amount'   => 'decimal:2',
        'total_visits'   => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function usageFee()
    {
        return $this->belongsTo(UsageFee::class, 'usage_fee_id');
    }
    
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
