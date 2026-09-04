<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invoice opened from the Billing list — the "Bill Details" screen.
 *
 * Carries the invoice summary the screen prints, the lines behind it, and what
 * has already been paid towards it, so the Pay Bill button knows what is left
 * without a second call.
 */
class BillDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hospital = $this->hospital ?: [];
        $isSettled = (bool) $this->is_settled;
        $outstanding = round((float) $this->computed_outstanding, 2);

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'service' => $this->service_title,

            'hospital' => [
                'uuid' => $hospital['uuid'] ?? null,
                'name' => $hospital['name'] ?? null,
                'logo' => $hospital['logo'] ?? null,
                'address' => $hospital['address'] ?? null,
            ],

            'currency' => config('services.paystack.currency', 'NGN'),
            'amount' => round((float) $this->computed_grand_total, 2),
            'amount_paid' => round((float) $this->amount_paid, 2),
            'outstanding' => $outstanding,
            'discount' => round((float) $this->discount, 2),
            'tax_amount' => round((float) $this->tax_amount, 2),

            'is_settled' => $isSettled,
            'status' => $isSettled ? 'Paid' : 'Outstanding',
            'can_pay' => !$isSettled,
            'payment_method' => $this->payment_method,

            'bill_date' => optional($this->billed_at)->format('Y-m-d'),
            'bill_date_label' => optional($this->billed_at)->format('j M Y'),
            'due_date' => $this->due_date,

            // What was billed for, one row per line item.
            'items' => collect($this->billingLogDetails ?: [])->map(fn($detail) => [
                'id' => $detail->id,
                'name' => $detail->item_name,
                'unit' => optional($detail->serviceUnit)->name,
                'quantity' => (int) $detail->quantity,
                'amount' => round((float) $detail->amount, 2),
                'amount_paid' => round((float) $detail->amount_paid, 2),
                'status' => $detail->status,
            ])->values(),

            // Only successful payments, so this reads as a receipt history
            // rather than a log of everything anyone ever attempted.
            'payments' => collect($this->payments ?: [])->map(fn($payment) => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => round((float) $payment->amount, 2),
                'channel' => $payment->channel,
                'paid_by' => $payment->is_support ? $payment->supporter_name : 'You',
                'is_support' => (bool) $payment->is_support,
                'paid_at' => optional($payment->paid_at)->toDateTimeString(),
            ])->values(),
        ];
    }
}
