<?php

namespace App\Http\Requests\Patient;

use Illuminate\Validation\Rules\Password;

class CreatePasswordRequest extends PatientRequest
{
    public function rules(): array
    {
        return [
            'token'    => 'required|string|max:128',
            // Mirrors the checklist the app shows while the password is typed.
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            // Stated explicitly so a missing confirmation says so, rather than
            // failing `confirmed` and reporting a mismatch that never happened.
            'password_confirmation' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'token.required'                 => 'Verify your invitation before creating a password.',
            'password.confirmed'             => 'The two passwords do not match.',
            'password.min'                   => 'Your password must be at least 8 characters long.',
            'password_confirmation.required' => 'Confirm your password using the password_confirmation field.',
        ];
    }
}
