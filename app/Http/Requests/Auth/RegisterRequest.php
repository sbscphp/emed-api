<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class RegisterRequest extends FormRequest
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
            // Hospital
            'hospital_name'       => 'required|string|max:255',
            'state_city'          => 'required|string|max:255',
            'registration_number' => 'required|string|max:100',
            'hospital_email'      => 'required|email|unique:tenants,email',
            'hospital_phoneno'    => 'required|string|max:20|unique:tenants,phone_number',
            'hospital_address'    => 'required|string|max:500',

            // Admin
            'admin_firstname'             => 'required|string|max:100',
            'admin_lastname'              => 'required|string|max:100',
            'admin_phoneno'               => 'required|string|max:20|unique:users,phone_number',
            'admin_email'                 => 'required|email|unique:users,email',
            'admin_password'              => 'required|confirmed|min:6',
            'admin_password_confirmation' => 'required_with:admin_password',
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
            // Hospital
            'hospital_name.required'       => 'Hospital name is required.',
            'state_city.required'          => 'State/City is required.',
            'registration_number.required' => 'Registration number is required.',
            'hospital_email.required'      => 'Hospital email is required.',
            'hospital_email.email'         => 'Hospital email must be a valid email address.',
            'hospital_email.unique'        => 'This hospital email is already in use.',
            'hospital_phoneno.required'    => 'Hospital phone number is required.',
            'hospital_phoneno.unique'      => 'This hospital phone number is already in use.',
            'hospital_address.required'    => 'Hospital address is required.',

            // Admin
            'admin_firstname.required'             => 'Admin first name is required.',
            'admin_lastname.required'              => 'Admin last name is required.',
            'admin_phoneno.required'               => 'Admin phone number is required.',
            'admin_phoneno.unique'                 => 'This admin phone number is already in use.',
            'admin_email.required'                 => 'Admin email is required.',
            'admin_email.email'                    => 'Admin email must be a valid email address.',
            'admin_email.unique'                   => 'This admin email is already in use.',
            'admin_password.required'              => 'Admin password is required.',
            'admin_password.confirmed'             => 'Passwords do not match.',
            'admin_password.min'                   => 'Password must be at least 6 characters.',
            'admin_password_confirmation.required_with' => 'Please confirm the password.',
        ];
    }
}
