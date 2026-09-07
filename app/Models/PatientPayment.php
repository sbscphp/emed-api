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
        'billing_ids' => 'array',
        'allocations' => 'array',
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
     * Whether this one charge was raised against several invoices at once.
     */
    public function getIsBulkAttribute(): bool
    {
        return count($this->billing_ids ?: []) > 1;
    }

    /**
     * Every invoice this payment is meant to settle, in the order the money is
     * spread across them, and always starting with the one it was raised
     * against.
     *
     * A single bill payment answers with just its own id, so callers never have
     * to ask which kind of payment they are holding.
     *
     * @return array<int, int>
     */
    public function getCoveredBillingIdsAttribute(): array
    {
        return collect([$this->billing_id])
            ->merge($this->billing_ids ?: [])
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * What this payment actually put on one invoice.
     *
     * A bulk charge is worth more than any single bill it clears, so a bill's
     * own screens ask for its share rather than reading `amount`. Anything but a
     * bulk charge is worth exactly what it says: the whole amount landed on the
     * one bill it was raised for.
     */
    public function amountAppliedTo($billId): float
    {
        foreach ($this->allocations ?: [] as $id => $amount) {
            if ((int) $id === (int) $billId) {
                return round((float) $amount, 2);
            }
        }

        // A bulk charge with nothing recorded against this bill has not been
        // confirmed yet — it has put nothing anywhere.
        return $this->is_bulk ? 0.0 : round((float) $this->amount, 2);
    }

    /**
     * Payments that actually landed.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::SUCCESS);
    }

    /**
     * Payments touching one invoice, however they were raised.
     *
     * A bill is no longer found by billing_id alone: a bulk checkout names one
     * invoice there and carries the rest in billing_ids, and a bill left out of
     * this would show its share of that charge nowhere.
     */
    public function scopeForBill($query, $billId)
    {
        return $query->where(function ($query) use ($billId) {
            $query->where('billing_id', (int) $billId)
                ->orWhereJsonContains('billing_ids', (int) $billId);
        });
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
}
