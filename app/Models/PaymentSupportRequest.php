<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A patient's request for help paying one bill, and the link that carries it.
 *
 * @see \App\Services\Patient\Billing\PaymentSupportService
 */
class PaymentSupportRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $connection = 'tenant';

    protected $casts = [
        'target_amount' => 'decimal:2',
        'raised_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const ACTIVE = 'Active';

    public const COMPLETED = 'Completed';

    public const EXPIRED = 'Expired';

    public const CANCELLED = 'Cancelled';

    public function billing()
    {
        return $this->belongsTo(BillingLog::class, 'billing_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function payments()
    {
        return $this->hasMany(PatientPayment::class, 'support_request_id');
    }

    /**
     * The contributions the progress bar is built from, newest first.
     */
    public function contributions()
    {
        return $this->hasMany(PatientPayment::class, 'support_request_id')
            ->where('status', PatientPayment::SUCCESS)
            ->orderBy('paid_at', 'DESC')
            ->orderBy('id', 'DESC');
    }

    /**
     * Whether the link still takes contributions.
     *
     * Expiry is answered here rather than by a scheduled job: a link that has
     * run past its date is closed the next time somebody opens it, so nothing
     * depends on a worker having run.
     */
    public function getIsOpenAttribute(): bool
    {
        if ($this->status !== self::ACTIVE) {
            return false;
        }

        return !$this->expires_at || $this->expires_at->isFuture();
    }

    /**
     * What is still needed, never below zero.
     */
    public function getOutstandingAmountAttribute(): float
    {
        return max(0, round((float) $this->target_amount - (float) $this->raised_amount, 2));
    }

    /**
     * How far along the bar is, capped at 100 so an over-contribution does not
     * render past the end of it.
     */
    public function getProgressPercentAttribute(): float
    {
        if ((float) $this->target_amount <= 0) {
            return 100.0;
        }

        return min(100, round(((float) $this->raised_amount / (float) $this->target_amount) * 100, 2));
    }

    /**
     * The link the patient shares.
     *
     * An accessor rather than something a service writes onto the model with
     * setAttribute: a value written that way lands in the attribute bag, counts
     * as dirty, and the next save() tries to write it as a column. Computed here
     * it cannot reach an UPDATE at all.
     */
    public function getShareUrlAttribute(): string
    {
        return rtrim(config('services.paystack.support_url'), '/') . '/' . $this->token;
    }

    /**
     * How many people have given, for the "N supporters" line.
     */
    public function getSupportersCountAttribute(): int
    {
        return $this->contributions->count();
    }

    /**
     * What the bill behind this request was for, in the words the patient's own
     * billing screen uses — a service name, never anything clinical.
     *
     * Same shape as the title PatientBillingService puts on a bill, and derived
     * the same way: the service unit the invoice lines share.
     */
    public function getServiceTitleAttribute(): string
    {
        $bill = $this->billing;

        if (!$bill) {
            return 'Hospital bill';
        }

        $units = $bill->billingLogDetails
            ->map(fn($detail) => optional($detail->serviceUnit)->name)
            ->filter()
            ->unique();

        if ($units->count() === 1) {
            return $units->first() . ' services';
        }

        return $units->count() > 1 ? 'Hospital services' : 'Hospital bill';
    }
}
