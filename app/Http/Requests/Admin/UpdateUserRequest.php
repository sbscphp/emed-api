<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            // 'fullname' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:15',
            // 'email' => 'nullable|email',
            'role' => 'required',
            'date_of_birth' => 'nullable|date|before:today',
            'status' => 'nullable|string|in:Active,Inactive',
            'is_active' => 'nullable|boolean',
            // 'password' => 'nullable|string|min:8',

            // Consultant rate card (applied only when the role is consultant).
            'use_default_rate'  => 'nullable|boolean',
            'first_visit_price' => 'nullable|numeric|min:0',
            'returning_price'   => 'nullable|numeric|min:0',
            'markup_type'       => 'nullable|in:fixed,percentage',
            'markup_value'      => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Custom error messages for validation.
     */
    public function messages(): array
    {
        return [
            // 'fullname.string' => 'Full name must be a valid string.',
            // 'fullname.max' => 'Full name may not be greater than 255 characters.',

            'first_name.string' => 'First name must be a valid string.',
            'first_name.max' => 'First name may not be greater than 255 characters.',

            'last_name.string' => 'Last name must be a valid string.',
            'last_name.max' => 'Last name may not be greater than 255 characters.',

            'phone_number.string' => 'Phone number must be a valid string.',
            'phone_number.max' => 'Phone number may not be greater than 15 characters.',

            // 'email.email' => 'Please provide a valid email address.',

            // 'role.exists' => 'The selected role is invalid.',

            'date_of_birth.date' => 'Date of birth must be a valid date.',
            'date_of_birth.before' => 'Date of birth must be before today.',

            'status.in' => 'Status must be either Active or Inactive.',

            // 'password.string' => 'Password must be a valid string.',
            // 'password.min' => 'Password must be at least 8 characters long.',
        ];
    }
}
