<?php

namespace App\Services\Billing;

use App\Mail\InvoiceMail;
use App\Models\BillingLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

/**
 * Renders a branded invoice PDF for a billing and emails it to the patient.
 * Shared by the staff (download/resend) and patient (self-download) paths.
 */
class InvoiceService
{
    /**
     * Build the invoice PDF (DomPDF instance) for a billing.
     */
    public function pdf(BillingLog $billing)
    {
        $billing->loadMissing(['patient', 'billingLogDetails.serviceUnit', 'transactions']);

        return Pdf::loadView('invoices.invoice', ['billing' => $billing])->setPaper('A4');
    }

    /**
     * Return a download response for the invoice PDF.
     */
    public function download(BillingLog $billing)
    {
        return $this->pdf($billing)->download($billing->invoice_number . '.pdf');
    }

    /**
     * Email the invoice PDF to the patient (or an override address).
     * Returns false only when no email address is available; a mail-delivery
     * failure is allowed to throw so callers can decide how to report it.
     */
    public function sendToPatient(BillingLog $billing, ?string $emailOverride = null): bool
    {
        $billing->loadMissing('patient');

        $email = $emailOverride ?: optional($billing->patient)->email;
        if (empty($email)) {
            return false;
        }

        $pdf = $this->pdf($billing)->output();
        Mail::to($email)->send(new InvoiceMail($billing, $pdf));

        return true;
    }
}
