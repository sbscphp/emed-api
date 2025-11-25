<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

            'patient_id' => 'required|exists:tenant.patients,id',
            'visit_id' => 'required|exists:tenant.patient_visits,id',
            'consultation_id' => 'required|exists:tenant.patient_visit_consultation,id',

            'medications' => 'required|array|min:1',
            'medications.*.drug_id' => 'required|integer',
            'medications.*.qualifier' => 'nullable|string',
            'medications.*.pharmacy_id' => 'nullable',
            'medications.*.dosage' => 'required|string',
            'medications.*.weight' => 'nullable|string',
            'medications.*.period' => 'required|string',
            'medications.*.duration' => 'required|string',
            'medications.*.route' => 'nullable|string',
            'medications.*.remark' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'The patient field is required.',
            'patient_id.exists'   => 'The selected patient does not exist in the system.',

            'visit_id.required' => 'The visit field is required.',
            'visit_id.exists'   => 'The selected visit does not exist in the system.',

            'consultation_id.required' => 'The consultation field is required.',
            'consultation_id.exists'   => 'The selected consultation does not exist in the system.',

            'medications.required' => 'At least one medication must be provided.',
            'medications.array'    => 'The medications field must be an array.',
            'medications.min'      => 'You must provide at least one medication.',

            'medications.*.drug_id.required' => 'The drug is required for each medication.',
            'medications.*.drug_id.integer'  => 'The drug ID must be a valid integer.',

            'medications.*.pharmacy_id.required'  => 'The pharmacy id is required for each medication.',

            'medications.*.qualifier.string' => 'The qualifier must be a string.',

            'medications.*.dosage.required' => 'The dosage is required for each medication.',
            'medications.*.dosage.string'   => 'The dosage must be a valid string.',

            'medications.*.weight.string' => 'The weight must be a valid string.',

            'medications.*.period.required' => 'The period is required for each medication.',
            'medications.*.period.string'   => 'The period must be a valid string.',

            'medications.*.duration.required' => 'The duration is required for each medication.',
            'medications.*.duration.string'   => 'The duration must be a valid string.',

            'medications.*.route.string'  => 'The route must be a valid string.',
            'medications.*.remark.string' => 'The remark must be a valid string.',
        ];
    }
}
