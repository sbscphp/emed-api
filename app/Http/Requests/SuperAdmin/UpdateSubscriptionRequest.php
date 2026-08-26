<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usage_fee_id' => 'sometimes|integer|exists:landlord.usage_fees,id',
            'license_fee' => 'sometimes|numeric|min:0',
            'license_start_date' => 'sometimes|date',
            'license_end_date' => 'sometimes|date|after:license_start_date',
        ];
    }

    public function messages(): array
    {
        return [
            'usage_fee_id.exists' => 'The selected usage fee does not exist.',
            'license_fee.numeric' => 'License fee must be a number.',
            'license_fee.min' => 'License fee must be at least 0.',
            'license_start_date.date' => 'License start date must be a valid date.',
            'license_end_date.date' => 'License end date must be a valid date.',
            'license_end_date.after' => 'License end date must be after the license start date.',
        ];
    }
}
