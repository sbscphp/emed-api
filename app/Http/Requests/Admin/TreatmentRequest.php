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
            'medications' => 'required|array|min:1',
            'medications.*.drug_id' => 'required|integer',
            'medications.*.drug' => 'nullable|string',
            'medications.*.qualifier' => 'nullable|string',
            'medications.*.medication' => 'nullable|string',
            'medications.*.dosage' => 'required|string',
            'medications.*.weight' => 'nullable|string',
            'medications.*.period' => 'required|string',
            'medications.*.duration' => 'required|string',
            'medications.*.route' => 'nullable|string',
            'medications.*.remark' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'medications.required' => 'At least one medication must be provided.',
            'medications.array' => 'Medications must be an array.',
            'medications.*.drug_id.required' => 'Each medication must have a drug ID.',
            'medications.*.drug_id.integer' => 'The drug ID must be a valid integer.',
            'medications.*.drug.string' => 'The drug name must be a valid string.',
            'medications.*.qualifier.string' => 'The qualifier must be a valid string.',
            'medications.*.medication.string' => 'The medication name must be a valid string.',
            'medications.*.dosage.required' => 'Each medication must have a dosage.',
            'medications.*.dosage.string' => 'The dosage must be a valid string.',
            'medications.*.weight.string' => 'The weight must be a valid string.',
            'medications.*.period.required' => 'Each medication must have a period.',
            'medications.*.period.string' => 'The period must be a valid string.',
            'medications.*.duration.required' => 'Each medication must have a duration.',
            'medications.*.duration.string' => 'The duration must be a valid string.',
            'medications.*.route.string' => 'The route must be a valid string.',
            'medications.*.remark.string' => 'The remark must be a valid string.',
        ];
    }



}
