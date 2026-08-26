<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class TenantOnboardingRequest extends FormRequest
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
            // Hospital Details
            'name' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $exists = DB::connection('landlord')
                        ->table('registrations')
                        ->where('name', $value)
                        ->exists();

                    if ($exists) {
                        $fail('The name already exists.');
                    }
                },
            ],

            'state_city' => 'required|string',
            'registration_number' => 'required|string',

            'phone_number' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    $exists = DB::connection('landlord')
                        ->table('registrations')
                        ->where('phone_number', $value)
                        ->exists();

                    if ($exists) {
                        $fail('Phone number already exists.');
                    }
                },
            ],

            'email' => [
                'required',
                'email:rfc,dns',
                function ($attribute, $value, $fail) {
                    $exists = DB::connection('landlord')
                        ->table('registrations')
                        ->where('email', $value)
                        ->exists();

                    if ($exists) {
                        $fail('Email already exists.');
                    }
                },
            ],

            'address' => 'required|string',
            'license' => 'nullable|file|max:3000',

            // Admin Details
            'admin_fullname' => 'required|string',
            'admin_role' => 'required|string',

            'admin_phone_number' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    $exists = DB::connection('landlord')
                        ->table('users')
                        ->where('phone_number', $value)
                        ->exists();

                    if ($exists) {
                        $fail('Admin phone number already exists.');
                    }
                },
            ],

            'admin_email' => [
                'required',
                'email:rfc,dns',
                function ($attribute, $value, $fail) {
                    $exists = DB::connection('landlord')
                        ->table('users')
                        ->where('email', $value)
                        ->exists();

                    if ($exists) {
                        $fail('Admin email already exists.');
                    }
                },
            ],

            'admin_password' => 'required|confirmed|min:6',
            'admin_password_confirm' => 'sometimes|same:admin_password',
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
            // Hospital Validation Messages
            'hospital_name.required'          => 'The hospital name is required.',
            'state_city.required'             => 'The state or city is required.',
            'registration_number.required'    => 'The registration number is required.',
            'hospital_email.required'         => 'The hospital email is required.',
            'hospital_email.email'            => 'Please provide a valid hospital email address.',
            'hospital_phone_number.required'  => 'The hospital phone number is required.',
            'address.required'                => 'The hospital address is required.',
            'license.file'                    => 'The license must be a valid file.',

            // Admin Validation Messages
            'admin_fullname.required'         => 'The admin full name is required.',
            'admin_role.required'             => 'The admin role is required.',
            'admin_phone_number.required'     => 'The admin phone number is required.',
            'admin_email.required'            => 'The admin email address is required.',
            'admin_email.email'               => 'Please provide a valid admin email address.',
            'admin_email.unique'              => 'The admin email address is already taken.',
            'admin_password.required'         => 'The admin password is required.',
            'admin_password.confirmed'        => 'The admin password confirmation does not match.',
            'admin_password.min'              => 'The admin password must be at least 6 characters.',
        ];
    }
}
