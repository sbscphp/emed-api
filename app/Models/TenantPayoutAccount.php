<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The bank account a hospital's share of a patient payment settles into.
 *
 * Lives on the landlord connection with the tenants it belongs to, because it
 * is the platform that settles, not the hospital.
 *
 * @see \App\Services\Billing\HospitalPayoutAccountService
 */
class TenantPayoutAccount extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $connection = 'landlord';

    protected $casts = [
        'commission_percent' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Whether this hospital can currently be paid — a bill cannot be charged
     * for until Paystack has given us a subaccount to split into.
     */
    public function getIsReadyAttribute(): bool
    {
        return $this->status === 'Active' && !empty($this->subaccount_code);
    }

    /**
     * The share Paystack should leave with the hospital.
     *
     * Paystack's `percentage_charge` on a subaccount is what the subaccount
     * keeps, which is the inverse of the commission eMed is configured to take.
     */
    public function hospitalSharePercent(): float
    {
        return round(100 - (float) $this->commission_percent, 2);
    }
}
