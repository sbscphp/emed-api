<?php

namespace App\Http\Requests;

use App\Rules\Auth\ValidateIdentifier;
use Illuminate\Foundation\Http\FormRequest;

class RadiologyResultRequest extends FormRequest
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
            'radiology_id' => 'required|exists:tenant.patient_visit_radiology,id',
            'patient_id' => 'required',
            'examination_type' => 'required',
            'clinical_indication' => 'required',
            'technique' => 'required',
            'findings' => 'required',
            'result_img' => 'required'
        ];
    }

    public function messages(): array
    {
        return [
            'radiology_id.required' => 'Radiology ID is required.',
            'patient_id.required' => 'Patient ID is required.',
            'examination_type.required' => 'Examination type is required.',
            'clinical_indication.required' => 'Clinical indication is required.',
            'technique.required' => 'Technique is required.',
            'findings.required' => 'Findings are required.',
            'result_img.required' => 'Result image is required.'
        ];
    }
}
