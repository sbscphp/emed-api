<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $guarded = ['id'];
    protected $connection = 'landlord';
    protected $table = 'subscriptions';

    protected $casts = [
        'license_fee' => 'decimal:2',
        'license_start_date' => 'date',
        'license_end_date' => 'date',
        'status' => 'string',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function usageFee()
    {
        return $this->belongsTo(UsageFee::class, 'usage_fee_id');
    }
}
