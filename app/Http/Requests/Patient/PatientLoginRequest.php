<?php

namespace App\Http\Requests\Patient;

class PatientLoginRequest extends PatientRequest
{
    public function rules(): array
    {
        return [
            'identifier' => 'required|string|max:255',
            'password'   => 'required|string',
            // A patient can attend several hospitals, so the one being signed
            // into is chosen on every login rather than remembered.
            'hospital_uuid' => 'required|string|max:36',
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required'    => 'Enter your email address or phone number.',
            'password.required'      => 'Enter your password.',
            'hospital_uuid.required' => 'Select the hospital you want to sign in to.',
        ];
    }
}
