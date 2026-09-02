<?php

namespace App\Http\Requests\Patient;

class VerifyPasswordResetOtpRequest extends PatientRequest
{
    public function rules(): array
    {
        return [
            'identifier' => 'required|string|max:255',
            'otp'        => 'required|digits:6',
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'Enter your email address or phone number.',
            'otp.required'        => 'Enter the 6-digit code we sent to your email.',
            'otp.digits'          => 'The code is 6 digits long.',
        ];
    }
}
