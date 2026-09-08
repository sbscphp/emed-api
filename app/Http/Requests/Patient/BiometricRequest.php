<?php

namespace App\Http\Requests\Patient;

class BiometricRequest extends PatientRequest
{
    public function rules(): array
    {
        return [
            // "Enable" and "Skip for Now" on the biometric screen, and the same
            // switch in settings later on.
            'enabled' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'enabled.required' => 'Tell us whether biometric sign in should be enabled.',
        ];
    }
}
