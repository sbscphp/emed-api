<?php

namespace App\Http\Requests\SuperAdmin;

use App\Rules\Auth\ValidateIdentifier;
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
            // Subscription-level rules
            'name' => 'required|string|max:255',

            // Applications array rules
            'applications' => 'required|array|min:1',
            'applications.*.application_id' => 'required',
            'applications.*.monthly_amount' => 'required',
            'applications.*.yearly_amount' => 'required',
            'applications.*.monthly_number_of_days' => 'required',
            'applications.*.yearly_number_of_days' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            // Subscription-level messages
            'name.required' => 'The subscription name is required.',
            'name.string' => 'The subscription name must be a valid string.',
            'name.max' => 'The subscription name may not be greater than 255 characters.',

            // Applications array messages
            'applications.required' => 'At least one application must be included.',
            'applications.array' => 'The applications field must be an array.',
            'applications.min' => 'You must provide at least one application.',

            'applications.*.application_id.required' => 'The application ID is required for each application.',
            'applications.*.monthly_amount.required' => 'The monthly amount is required for each application.',
            'applications.*.yearly_amount.required' => 'The yearly amount is required for each application.',
            'applications.*.monthly_number_of_days.required' => 'The monthly number of days is required for each application.',
            'applications.*.yearly_number_of_days.required' => 'The yearly number of days is required for each application.',
        ];
    }
}
