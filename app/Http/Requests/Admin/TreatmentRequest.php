<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TreatmentRequest extends FormRequest
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
            'medications' => 'required|array',
            'medications.*.lab_dept' => 'required|string',
            'medications.*.test_name' => 'required|string',
            'medications.*.medication' => 'required|string',
            'medications.*.dosage' => 'required|string',
            'medications.*.weight' => 'nullable|string',
            'medications.*.period' => 'required|string',
            'medications.*.duration' => 'required|string',
            'medications.*.route' => 'required|string',
            'medications.*.remark' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'medications.required' => 'At least one medication is required.',
            'medications.array' => 'Medications must be an array.',
            'medications.*.lab_dept.required' => 'Lab department is required.',
            'medications.*.test_name.required' => 'Test name is required.',
            'medications.*.medication.required' => 'Medication name is required.',
            'medications.*.dosage.required' => 'Dosage is required.',
            'medications.*.period.required' => 'Period is required.',
            'medications.*.duration.required' => 'Duration is required.',
            'medications.*.route.required' => 'Route of administration is required.',
        ];
    }

}
