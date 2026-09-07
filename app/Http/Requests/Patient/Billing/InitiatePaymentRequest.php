<?php

namespace App\Http\Requests\Patient\Billing;

use App\Http\Requests\Patient\PatientRequest;
use Illuminate\Validation\Rule;

/**
 * Starting a checkout for the patient's own bills — one of them, or all of them.
 *
 * Shared by /billing/{id}/pay and /billing/pay-all, because the body is the
 * same either way: what differs is which bills the server puts the money on,
 * which is its decision and not the client's. /pay-all in particular takes no
 * ids — the outstanding set is read on the server.
 *
 * `amount` is optional: leaving it out pays the whole outstanding balance, which
 * is what the Pay Bill and Pay Now buttons do. Sending one is a part payment,
 * and the service refuses anything larger than is owed — by the one bill for
 * /pay, and across every outstanding bill for /pay-all, where it clears the
 * oldest invoices first.
 *
 * `channels` is constrained to the two the app offers. It is validated rather
 * than passed through so a client cannot open a channel — USSD, QR, a saved
 * authorization — that the flow has not been designed around.
 */
class InitiatePaymentRequest extends PatientRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'min:1'],
            'email' => ['nullable', 'email', 'max:255'],
            'channels' => ['nullable', 'array', 'min:1'],
            'channels.*' => ['string', Rule::in(['card', 'bank_transfer'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'Enter an amount of at least 1.',
            'channels.*.in' => 'Choose either card or bank transfer.',
        ];
    }
}
