<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One payment attempt.
 *
 * Serves both the moment a checkout is started and the moment it is confirmed,
 * which is why it carries `status` as well as the amounts: the app polls the
 * same shape either way and switches on `is_successful`.
 *
 * The gateway payload is never exposed. It is kept on the row for
 * reconciliation, and it carries the payer's card metadata.
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'billing_id' => $this->billing_id,

            'amount' => round((float) $this->amount, 2),
            'currency' => $this->currency,
            'channel' => $this->channel,

            'status' => $this->status,
            'is_successful' => (bool) $this->is_successful,
            'failure_reason' => $this->failure_reason,

            'paid_by' => $this->is_support ? $this->supporter_name : 'You',
            'is_support' => (bool) $this->is_support,

            'paid_at' => optional($this->paid_at)->toDateTimeString(),
            'paid_on' => optional($this->paid_at)->format('d F Y'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
