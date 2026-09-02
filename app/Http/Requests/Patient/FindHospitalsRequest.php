<?php

namespace App\Http\Requests\Patient;

class FindHospitalsRequest extends PatientRequest
{
    public function rules(): array
    {
        return [
            // Either of the two things a hospital could have registered them
            // with, matching what the app's single input accepts.
            'identifier' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'Enter the email address or phone number your hospital registered you with.',
        ];
    }
}
