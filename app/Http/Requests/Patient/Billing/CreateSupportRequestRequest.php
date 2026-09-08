<?php

namespace App\Http\Requests\Patient\Billing;

use App\Http\Requests\Patient\PatientRequest;

/**
 * Generating the link a patient shares when asking for help with a bill.
 *
 * The only thing the patient supplies is an optional note. The amount is not
 * theirs to set — it is what the bill is outstanding, read from the invoice, so
 * a link can never ask for more than the hospital is owed.
 */
class CreateSupportRequestRequest extends PatientRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.max' => 'Keep your note to 500 characters or fewer.',
        ];
    }
}
