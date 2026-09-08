<?php

namespace App\Http\Requests\Patient\Billing;

use App\Http\Requests\Patient\PatientRequest;

/**
 * A friend contributing towards a bill through a shared link.
 *
 * This is the one request in the patient namespace that runs unauthenticated,
 * so everything it accepts is spelled out rather than inferred from a token. The
 * email is required because Paystack needs somewhere to send the receipt, and
 * because it is the only handle a supporter has on their own payment if they
 * later need to ask about it.
 *
 * Nothing here identifies the patient. The bill is found from the link's token,
 * which the URL carries and this body cannot influence.
 */
class ContributeRequest extends PatientRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'is_anonymous' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Choose how much you would like to give.',
            'amount.min' => 'Enter an amount of at least 1.',
            'email.required' => 'We need an email address to send your receipt to.',
        ];
    }
}
