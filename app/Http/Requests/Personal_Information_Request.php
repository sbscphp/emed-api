<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Personal_Information_Request extends FormRequest
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
            'phoneno' => "nullable|numeric|size:15",
            'email' => "nullable|email",
            'dob' => "nullable|date",
            'patient_type' => "nullable|string",
            // 'genotype' => "nullable|string",
            // 'bloodgroup' => "nullable|string",
            'marital_status' => "nullable|string",
            'tribe' => "nullable|string",
            'homeaddress' => "nullable|string",
            'occupation' => "nullable|string",


            'patient_id' => 'nullable|exists:tenant.patients,id',
            'firstname' => 'nullable|string',
            'lastname' => 'nullable|string',
            'gender' => 'nullable|string',
            'relationship' => 'nullable|string',
            // 'phoneno',
            'homeaddress' => 'nullable|string',
            'stateoforigin' => 'nullable|string',
            'lga' => 'nullable|string',

        ];
    }
}
