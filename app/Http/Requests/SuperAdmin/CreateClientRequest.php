<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hospital_name' => 'required|string|max:255',
            'country' => 'required|string|max:100',
            'state_city' => 'required|string|max:255',
            'registration_number' => 'required|string|max:100',
            'hospital_address' => 'required|string|max:500',
            'hospital_type' => 'required|string|max:100',
            'hospital_email' => 'required|email|unique:landlord.tenants,email',
            'hospital_phoneno' => 'required|string|max:20|unique:landlord.tenants,phone_number',
            'admin_firstname' => 'required|string|max:100',
            'admin_lastname' => 'required|string|max:100',
            'admin_phoneno' => 'required|string|max:20|unique:landlord.users,phone_number',
            'admin_email' => 'required|email|unique:landlord.users,email',
            'admin_password' => 'required|confirmed|min:6',
            'admin_password_confirmation' => 'required_with:admin_password|string',
            'usage_fee_id' => 'required|integer|exists:landlord.usage_fees,id',
            'license_fee' => 'required|numeric|min:0',
            'license_start_date' => 'required|date',
            'license_end_date' => 'required|date|after:license_start_date',
        ];
    }

    public function messages(): array
    {
        return [
            'hospital_name.required' => 'Hospital name is required.',
            'country.required' => 'Country is required.',
            'state_city.required' => 'State or region is required.',
            'registration_number.required' => 'Registration number is required.',
            'hospital_address.required' => 'Hospital address is required.',
            'hospital_type.required' => 'Hospital type is required.',
            'hospital_email.required' => 'Hospital email is required.',
            'hospital_email.email' => 'Hospital email must be valid.',
            'hospital_email.unique' => 'Hospital email is already registered.',
            'hospital_phoneno.required' => 'Hospital phone number is required.',
            'hospital_phoneno.unique' => 'Hospital phone number is already registered.',
            'admin_firstname.required' => 'Admin first name is required.',
            'admin_lastname.required' => 'Admin last name is required.',
            'admin_phoneno.required' => 'Admin phone number is required.',
            'admin_phoneno.unique' => 'Admin phone number is already registered.',
            'admin_email.required' => 'Admin email is required.',
            'admin_email.email' => 'Admin email must be valid.',
            'admin_email.unique' => 'Admin email is already registered.',
            'admin_password.required' => 'Admin password is required.',
            'admin_password.confirmed' => 'Password confirmation does not match.',
            'license_fee.required' => 'License fee is required.',
            'license_fee.numeric' => 'License fee must be a number.',
            'license_start_date.required' => 'License start date is required.',
            'license_start_date.date' => 'License start date must be a valid date.',
            'license_end_date.required' => 'License end date is required.',
            'license_end_date.date' => 'License end date must be a valid date.',
            'license_end_date.after' => 'License end date must be after the license start date.',
            'usage_fee_id.required' => 'A usage fee must be selected.',
            'usage_fee_id.exists' => 'The selected usage fee does not exist.',
        ];
    }
}
