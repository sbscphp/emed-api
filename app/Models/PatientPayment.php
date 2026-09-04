<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One attempt to pay a bill, from the app or from a shared support link.
 *
 * Written when the payer is sent to Paystack and settled when the reference is
 * verified, so a payment is never lost merely because nobody came back.
 *
 * @see \App\Services\Patient\Billing\PatientPaymentService
 */
class PatientPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $connection = 'tenant';

    protected $casts = [
        'amount' => 'decimal:2',
        'hospital_amount' => 'decimal:2',
        'platform_amount' => 'decimal:2',
        'fees' => 'decimal:2',
        'is_anonymous' => 'boolean',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * The statuses a reference can end up in. Only SUCCESS moves money onto a
     * bill; the rest exist so a stuck payment can be told apart from a
     * cancelled one when someone asks where their money went.
     */
    public const PENDING = 'Pending';

    public const SUCCESS = 'Success';

    public const FAILED = 'Failed';

    public const ABANDONED = 'Abandoned';

    public function billing()
    {
        return $this->belongsTo(BillingLog::class, 'billing_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function supportRequest()
    {
        return $this->belongsTo(PaymentSupportRequest::class, 'support_request_id');
    }

    public function getIsSuccessfulAttribute(): bool
    {
        return $this->status === self::SUCCESS;
    }

    /**
     * Whether a friend paid this rather than the patient.
     */
    public function getIsSupportAttribute(): bool
    {
        return !empty($this->support_request_id);
    }

    /**
     * How the supporter is named on the patient's progress list.
     */
    public function getSupporterNameAttribute(): string
    {
        if ($this->is_anonymous || empty($this->payer_name)) {
            return 'Anonymous';
        }

        return $this->payer_name;
    }

    /**
     * Payments that actually landed.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::SUCCESS);
    }
}
