<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AdminRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Adjust this based on your authorization logic
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'fullname'     => 'required|string',
            'role'         => 'required|string',
            'phone_number' => 'required|numeric',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|confirmed|min:6',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [

            'fullname.required'  => 'The full name is required.',
            'role.required'      => 'The role is required.',
            'phone_number.required' => 'The phone number is required.',
            'email.required'     => 'The email address is required.',
            'email.email'        => 'Please provide a valid email address.',
            'email.unique'       => 'The email address is already taken.',
            'password.required'  => 'The password is required.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.min'       => 'The password must be at least 6 characters.',
        ];
    }
}
