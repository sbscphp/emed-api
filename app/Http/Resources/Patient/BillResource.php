<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the patient app's Billing list.
 *
 * The amounts are the derived ones the service attached, not the stored columns:
 * `amount_outstanding` is left at zero on a good number of rows that are plainly
 * not paid, so a screen built on it would show a settled bill the patient has
 * never paid. The status is answered as a word and as a boolean, so the app
 * switches on the boolean and never on the wording.
 */
class BillResource extends JsonResource
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

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'service' => $this->service_title,
            'hospital' => $hospital['name'] ?? null,

            'amount' => round((float) $this->computed_grand_total, 2),
            'amount_paid' => round((float) $this->amount_paid, 2),
            'outstanding' => round((float) $this->computed_outstanding, 2),
            'currency' => config('services.paystack.currency', 'NGN'),

            'is_settled' => $isSettled,
            'status' => $isSettled ? 'Paid' : 'Outstanding',
            'can_pay' => !$isSettled,

            'date' => optional($this->billed_at)->format('Y-m-d'),
            'date_label' => optional($this->billed_at)->format('d F Y'),
            'due_date' => $this->due_date,
        ];
    }
}
