<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionPlanRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'nullable|string',

            'billing_cycles' => 'required_without_all:prices,cycles|array|min:1',
            'billing_cycles.*.billing_cycle' => 'required_with:billing_cycles|string',
            'billing_cycles.*.price' => 'required_with:billing_cycles|numeric|min:0',

            'prices' => 'required_without_all:billing_cycles,cycles|array|min:1',
            'prices.*.billing_cycle' => 'required_with:prices|string',
            'prices.*.price' => 'required_with:prices|numeric|min:0',

            'cycles' => 'required_without_all:billing_cycles,prices|array|min:1',
            'cycles.*.billing_cycle' => 'required_with:cycles|string',
            'cycles.*.price' => 'required_with:cycles|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The subscription plan name is required.',
            'name.string' => 'The subscription plan name must be a valid string.',
            'name.max' => 'The subscription plan name may not be greater than 255 characters.',
            'description.required' => 'The description is required.',
            'description.string' => 'The description must be a valid string.',

            'billing_cycles.required_without_all' => 'At least one billing cycle with price is required.',
            'billing_cycles.array' => 'The billing cycles field must be an array.',
            'billing_cycles.min' => 'You must provide at least one billing cycle.',
            'billing_cycles.*.billing_cycle.required_with' => 'The billing cycle (e.g. Monthly, Quarterly, Yearly) is required.',
            'billing_cycles.*.price.required_with' => 'The price for each billing cycle is required.',
            'billing_cycles.*.price.numeric' => 'The price must be a valid number.',
            'billing_cycles.*.price.min' => 'The price cannot be negative.',

            'prices.required_without_all' => 'At least one billing cycle with price is required.',
            'prices.array' => 'The prices field must be an array.',
            'prices.min' => 'You must provide at least one price option.',
            'prices.*.billing_cycle.required_with' => 'The billing cycle is required.',
            'prices.*.price.required_with' => 'The price is required.',

            'cycles.required_without_all' => 'At least one billing cycle with price is required.',
            'cycles.array' => 'The cycles field must be an array.',
            'cycles.min' => 'You must provide at least one billing cycle.',
        ];
    }
}
