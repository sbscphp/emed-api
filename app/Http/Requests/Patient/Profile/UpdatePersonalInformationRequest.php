<?php

namespace App\Http\Requests\Patient\Profile;

use App\Http\Requests\Patient\PatientRequest;
use Illuminate\Validation\Rule;

/**
 * The "Personal information" form.
 *
 * Every field is optional so the screen can save one line at a time. The email
 * here is the one this hospital has on file for the patient — it does not
 * change the address they sign in with, which lives on the shared account and
 * has its own flow.
 */
class UpdatePersonalInformationRequest extends PatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in(['Male', 'Female', 'Other'])],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'home_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'marital_status' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Enter your full name.',
            'date_of_birth.before' => 'Your date of birth has to be in the past.',
            'gender.in' => 'Choose Male, Female or Other.',
            'email.email' => 'Enter a valid email address.',
        ];
    }
}
