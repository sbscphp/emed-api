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
            //'password' => 'required|string|min:8',
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
        ];
    }
}
