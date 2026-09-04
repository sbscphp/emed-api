<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The patient's own view of a support request — the "Request payment support"
 * screen once a link has been generated.
 *
 * Carries the link to share, how far along the bar is, and who has given so far.
 * A supporter who asked to stay anonymous is already named "Anonymous" by the
 * model, so there is nothing here to remember to hide.
 */
class SupportRequestResource extends JsonResource
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
            'billing_id' => $this->billing_id,
            'service' => $this->service_title,

            'share_url' => $this->share_url,
            'message' => $this->message,

            'currency' => config('services.paystack.currency', 'NGN'),
            'target_amount' => round((float) $this->target_amount, 2),
            'raised_amount' => round((float) $this->raised_amount, 2),
            'outstanding_amount' => $this->outstanding_amount,
            'progress_percent' => $this->progress_percent,
            'supporters_count' => (int) $this->supporters_count,

            'status' => $this->status,
            'is_open' => (bool) $this->is_open,
            'expires_at' => optional($this->expires_at)->toDateTimeString(),
            'expires_on' => optional($this->expires_at)->format('d F Y'),
            'completed_at' => optional($this->completed_at)->toDateTimeString(),

            // "Recent support" — newest first, as the model orders them.
            'supporters' => collect($this->contributions ?: [])->map(fn($payment) => [
                'name' => $payment->supporter_name,
                'amount' => round((float) $payment->amount, 2),
                'paid_at' => optional($payment->paid_at)->toDateTimeString(),
                'paid_on' => optional($payment->paid_at)->format('d F Y'),
            ])->values(),
        ];
    }
}
