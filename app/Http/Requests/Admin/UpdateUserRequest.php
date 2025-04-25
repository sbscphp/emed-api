<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
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
            'fullname' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:15',
            'email' => 'nullable|email',
            'role' => 'nullable|exists:tenant.roles,name',
            'date_of_birth' => 'nullable|date|before:today',
            'password' => 'nullable|string|min:8',
        ];
    }

    /**
     * Custom error messages for validation.
     */
    public function messages(): array
    {
        return [
            'phone_number.unique' => 'The phone number is already in use.',
            'email.unique' => 'The email address is already registered.',
            'date_of_birth.before' => 'Date of birth must be a past date.',
        ];
    }
}
