<?php

namespace App\Http\Requests\Patient;

use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends PatientRequest
{
    public function rules(): array
    {
        return [
            // Handed over by verify-reset-otp, not typed by the patient.
            'reset_token' => 'required|string|max:128',
            'password'    => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            // Stated explicitly so a missing confirmation says so, rather than
            // failing `confirmed` and reporting a mismatch that never happened.
            'password_confirmation' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'reset_token.required'           => 'Verify the code we sent you before setting a new password.',
            'password.confirmed'             => 'The two passwords do not match.',
            'password.min'                   => 'Your password must be at least 8 characters long.',
            'password_confirmation.required' => 'Confirm your password using the password_confirmation field.',
        ];
    }
}
