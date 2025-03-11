<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class HospitalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Change this if you need to check for specific user permissions
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name'                => 'required|string',
            'state_city'          => 'required|string',
            'registration_number' => 'required|string',
            'email'               => 'required|email',
            'phone_number'        => 'required|numeric',
            'address'             => 'required|string',
            'license' => 'nullable|file|max:3000',
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
            'name.required' => 'The hospital name is required.',
            'state_city.required' => 'The state or city is required.',
            'registration_number.required' => 'The registration number is required.',
            'email.required' => 'The hospital email is required.',
            'email.email' => 'Please provide a valid email address.',
            'phone_number.required' => 'The phone number is required.',
            'address.required' => 'The hospital address is required.',
            'license.file' => 'The license must be a valid file.',
        ];
    }
}
