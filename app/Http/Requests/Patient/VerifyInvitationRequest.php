<?php

namespace App\Http\Requests\Patient;

class VerifyInvitationRequest extends PatientRequest
{
    public function rules(): array
    {
        return [
            // Either of the two things a hospital could have registered them with.
            'identifier'    => 'required|string|max:255',
            'hospital_uuid' => 'required|string|max:36',
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required'    => 'Enter the email address or phone number your hospital registered you with.',
            'hospital_uuid.required' => 'Select the hospital that registered you.',
        ];
    }
}
