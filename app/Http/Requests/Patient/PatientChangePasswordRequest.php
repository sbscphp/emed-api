<?php

namespace App\Http\Requests\Patient;

use Illuminate\Validation\Rules\Password;

class PatientChangePasswordRequest extends PatientRequest
{
    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', 'different:current_password', Password::min(8)->mixedCase()->numbers()->symbols()],
            // Stated explicitly so a missing confirmation says so, rather than
            // failing `confirmed` and reporting a mismatch that never happened.
            'password_confirmation' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed'             => 'The two passwords do not match.',
            'password.different'             => 'Your new password must be different from your current one.',
            'password_confirmation.required' => 'Confirm your password using the password_confirmation field.',
        ];
    }
}
