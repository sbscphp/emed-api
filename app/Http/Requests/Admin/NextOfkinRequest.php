<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class NextOfkinRequest extends FormRequest
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
            // 'patient_id' => 'required|exists:patients,id',
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'gender' => 'required|string',
            'phoneno' => 'required|alpha_num',
            'stateoforigin' => 'nullable|string',
            'lga' => 'nullable|string',
            'homeaddress' => 'required|string',
            'relationship' => 'required|string'
        ];
    }

    public function messages()
    {
        return [
            // 'patient_id.required' => 'Patient ID is required.',
            // 'patient_id.exists' => 'Patient ID must belong in Patients table',
            'firstname.required' => 'The first name field is required.',
            'firstname.string' => 'The first name must be a valid string.',
            'lastname.required' => 'The last name field is required.',
            'lastname.string' => 'The last name must be a valid string.',
            'gender.required' => 'The gender field is required.',
            'gender.in' => 'The gender must be either male or female.',
            'phoneno.required' => 'The phone number field is required.',
            'relationship.required' => 'The relationship field is required.',
            'relationship.string' => 'The relationship must be a valid string.',
            'homeaddress' => 'The home address field is required.',
            'homeaddress' => 'The home address must be a valid string',
        ];
    }
}
