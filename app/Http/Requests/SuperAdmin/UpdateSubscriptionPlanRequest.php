<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|string',

            'billing_cycles' => 'sometimes|array|min:1',
            'billing_cycles.*.billing_cycle' => 'required_with:billing_cycles|string',
            'billing_cycles.*.price' => 'required_with:billing_cycles|numeric|min:0',

            'prices' => 'sometimes|array|min:1',
            'prices.*.billing_cycle' => 'required_with:prices|string',
            'prices.*.price' => 'required_with:prices|numeric|min:0',

            'cycles' => 'sometimes|array|min:1',
            'cycles.*.billing_cycle' => 'required_with:cycles|string',
            'cycles.*.price' => 'required_with:cycles|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'The subscription plan name must be a valid string.',
            'name.max' => 'The subscription plan name may not be greater than 255 characters.',
            'description.string' => 'The description must be a valid string.',
            'billing_cycles.array' => 'The billing cycles field must be an array.',
            'billing_cycles.*.billing_cycle.required_with' => 'The billing cycle is required for each item.',
            'billing_cycles.*.price.required_with' => 'The price is required for each billing cycle.',
            'billing_cycles.*.price.numeric' => 'The price must be a valid number.',
            'billing_cycles.*.price.min' => 'The price cannot be negative.',
        ];
    }
}
