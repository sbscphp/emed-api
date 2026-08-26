<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:landlord.users,email',
            'phone_number' => 'nullable|string|max:20|unique:landlord.users,phone_number',
            'status'       => 'required|string|in:Active,Inactive',
            'role_id'      => 'required|integer|exists:landlord.super_admin_roles,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => 'User name is required.',
            'email.required'        => 'Email address is required.',
            'email.email'           => 'Please provide a valid email address.',
            'email.unique'          => 'This email address is already registered.',
            'phone_number.unique'   => 'This phone number is already registered.',
            'status.required'       => 'Account status is required.',
            'status.in'             => 'Status must be either Active or Inactive.',
            'role_id.required'      => 'A role must be selected.',
            'role_id.exists'        => 'The selected role does not exist.',
        ];
    }
}
