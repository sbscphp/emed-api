<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The receipt sent out once a patient payment has been confirmed.
 *
 * Carries the rendered PDF rather than a model or an id, so nothing about this
 * mail depends on a tenant database connection being the current one when it is
 * built. That is what lets it be sent from inside the settlement flow without
 * the mailer having to know which hospital it is standing in, and what would let
 * it be queued later without the worker needing to switch connections first.
 *
 * @see \App\Services\Patient\Billing\PatientPaymentService::emailReceipt()
 */
class PatientPaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $data  everything the view prints
     * @param  string  $pdf  the rendered receipt, as raw bytes
     * @param  string  $fileName  what the attachment is called
     */
    public function __construct(
        public array $data,
        public string $pdf,
        public string $fileName,
    ) {}

    public function build()
    {
        $hospital = $this->data['hospitalName'] ?? config('app.name');

        return $this->subject("Payment Receipt — {$hospital} ({$this->data['reference']})")
            ->view('emails.patient-payment-receipt')
            ->with($this->data + ['fileName' => $this->fileName])
            ->attachData($this->pdf, $this->fileName, [
                'mime' => 'application/pdf',
            ]);
    }
}
