<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('id');

        return [
            'hospital_name' => 'required|string|max:255',
            'country' => 'required|string|max:100',
            'state_city' => 'required|string|max:255',
            'registration_number' => 'required|string|max:100',
            'hospital_address' => 'required|string|max:500',
            'hospital_type' => 'required|string|max:100',
            'hospital_email' => [
                'required',
                'email',
                Rule::unique('landlord.tenants', 'email')->ignore($tenantId),
            ],
            'hospital_phoneno' => [
                'required',
                'string',
                'max:20',
                Rule::unique('landlord.tenants', 'phone_number')->ignore($tenantId),
            ],
            'usage_fee_id' => 'required|integer|exists:landlord.usage_fees,id',
            'license_fee' => 'required|numeric|min:0',
            'license_start_date' => 'required|date',
            'license_end_date' => 'required|date|after:license_start_date',
            'status' => ['sometimes', Rule::in(['Active', 'Inactive'])],
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
            'license_fee.required' => 'License fee is required.',
            'license_fee.numeric' => 'License fee must be a number.',
            'license_start_date.required' => 'License start date is required.',
            'license_start_date.date' => 'License start date must be a valid date.',
            'license_end_date.required' => 'License end date is required.',
            'license_end_date.date' => 'License end date must be a valid date.',
            'license_end_date.after' => 'License end date must be after the license start date.',
            'usage_fee_id.required' => 'A usage fee must be selected.',
            'usage_fee_id.exists' => 'The selected usage fee does not exist.',
            'status.in' => 'Status must be either Active or Inactive.',
        ];
    }
}
