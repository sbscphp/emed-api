<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class AssignClientUsageFeeRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.required' => 'Please select a hospital.',
            'tenant_id.integer' => 'Invalid hospital selection.',
            'tenant_id.exists' => 'The selected hospital does not exist.',
            'usage_fee_id.required' => 'Please select a usage fee.',
            'usage_fee_id.integer' => 'Invalid usage fee selection.',
            'usage_fee_id.exists' => 'The selected usage fee does not exist.',
        ];
    }
}
