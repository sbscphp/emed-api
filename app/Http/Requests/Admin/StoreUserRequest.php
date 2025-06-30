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
            'phone_number' => 'required|string|max:15|unique:tenant.users,phone_number',
           // 'email' => 'required|email|unique:tenant.users,email',
             'email' => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    $exists = DB::connection('tenant')
                        ->table('users')
                        ->where('email', $value)
                        ->exists();

                    if ($exists) {
                        $fail('this email already exist');
                    }
                },
            ],
            //'role' => 'required|exists:tenant.roles,name',
              'role' => [
                'required',
                'max:255',
                function ($attribute, $value, $fail) {
                    $exists = DB::connection('tenant')
                        ->table('roles')
                        ->where('name', $value)
                        ->exists();

                    if (!$exists) {
                        $fail("The selected role doesn't exist.");
                    }
                },
            ],
            'date_of_birth' => 'required|date|before:today',
            'password' => 'required|string|min:8',
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
