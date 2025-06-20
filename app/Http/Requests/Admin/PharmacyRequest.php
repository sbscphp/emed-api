<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class PharmacyRequest extends FormRequest
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
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'nullable|string',
            'address' => 'nullable|string',
            'state_id' => 'required|exists:tenant.states,id',
            'phone_number' => 'nullable|string|max:20',
            'opening_time' => 'required|date_format:H:i',
            'closing_time' => 'required|date_format:H:i|after:opening_time',
            'assigned_pharmacist' => 'nullable|exists:users,id',
            'license_number' => 'nullable|string|max:255',
          //  'email_address' => 'nullable|email|max:255|exists:tenant.pharmacies,email_address',
          // 'email_address' => 'nullable|email|max:255|exists:pharmacies,email_address',
            // 'pharmacy_id' => 'required|string|unique:tenant.pharmacies,pharmacy_id',
           'email_address' => [
                    'nullable',
                    'email',
                    'max:255',
                    Rule::exists('pharmacies', 'email_address')->connection('tenant'),
                ],
            'active' => 'boolean',
        ];
    }
}
