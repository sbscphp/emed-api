<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
class StoreUserRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'fullname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:15',
           // 'email' => 'required|email|unique:tenant.users,email',
             'email' => 'required|email|max:255',
             'role' => 'required',
            'date_of_birth' => 'required|date|before:today',
            'status' => 'nullable|string|in:Active,Inactive',
            //'password' => 'required|string|min:8',

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
            'phone_number.unique' => 'The phone number is already in use.',
            'email' => 'The email address field is required.',
            'date_of_birth.before' => 'Date of birth must be a past date.',
            'status.in' => 'Status must be either Active or Inactive.',
        ];
    }
}
