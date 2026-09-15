<?php

namespace App\Mail;

use App\Models\BillingLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $pdf Raw PDF bytes to attach.
     */
    public function __construct(public BillingLog $billing, public string $pdf) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->billing->invoice_number} — Enugu International Hospital",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: ['billing' => $this->billing],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->billing->invoice_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
