<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class CreateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:landlord.tenants,id',
            'usage_fee_id' => 'required|integer|exists:landlord.usage_fees,id',
            'license_start_date' => 'required|date',
            'license_end_date' => 'required|date|after:license_start_date',
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.required' => 'Hospital/Tenant is required.',
            'tenant_id.exists' => 'The selected hospital/tenant does not exist.',
            'usage_fee_id.required' => 'Usage fee is required.',
            'usage_fee_id.exists' => 'The selected usage fee does not exist.',
            'license_start_date.required' => 'License start date is required.',
            'license_start_date.date' => 'License start date must be a valid date.',
            'license_end_date.required' => 'License end date is required.',
            'license_end_date.date' => 'License end date must be a valid date.',
            'license_end_date.after' => 'License end date must be after the license start date.',
        ];
    }
}
