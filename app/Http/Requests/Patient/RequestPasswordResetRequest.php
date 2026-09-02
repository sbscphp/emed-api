<?php

namespace App\Http\Requests\Patient;

class RequestPasswordResetRequest extends PatientRequest
{
    public function rules(): array
    {
        return [
            // The same input the login screen accepts, so "Forget Password?"
            // can carry over whatever the patient already typed.
            'identifier' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'Enter your email address or phone number.',
        ];
    }
}
