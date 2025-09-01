<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SurgeryRequest extends FormRequest
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
            'recommended_surgery' => 'nullable|string|max:255',
            'proposed_date' => 'nullable|date',
            'surgery_type' => 'nullable|string|max:255',
            'surgery_category' => 'nullable|string|max:255',
            'team' => 'nullable|array',
            'team.*' => 'nullable|string|max:255',
            'anaesthesia_type' => 'nullable|string|max:255',
            'preup_instructions' => 'nullable|string',
            'postup_instructions' => 'nullable|string',
            'duration' => 'nullable|string|max:255',
            'consent_status' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'The patient field is required.',
            'patient_id.exists'   => 'The selected patient does not exist.',

            'visit_id.required' => 'The visit field is required.',
            'visit_id.exists'   => 'The selected visit does not exist.',

            'consultation_id.required' => 'The consultation field is required.',
            'consultation_id.exists'   => 'The selected consultation does not exist.',

            'recommended_surgery.string' => 'The recommended surgery must be a valid text.',
            'recommended_surgery.max'    => 'The recommended surgery may not be greater than 255 characters.',

            'proposed_date.date' => 'The proposed date must be a valid date.',

            'surgery_type.string' => 'The surgery type must be a valid text.',
            'surgery_type.max'    => 'The surgery type may not be greater than 255 characters.',

            'surgery_category.string' => 'The surgery category must be a valid text.',
            'surgery_category.max'    => 'The surgery category may not be greater than 255 characters.',

            'team.array'      => 'The team field must be an array.',
            'team.*.string'   => 'Each team member must be a valid text.',
            'team.*.max'      => 'Each team member may not be greater than 255 characters.',

            'anaesthesia_type.string' => 'The anaesthesia type must be a valid text.',
            'anaesthesia_type.max'    => 'The anaesthesia type may not be greater than 255 characters.',

            'preup_instructions.string'  => 'The pre-operative instructions must be a valid text.',
            'postup_instructions.string' => 'The post-operative instructions must be a valid text.',

            'duration.string' => 'The duration must be a valid text.',
            'duration.max'    => 'The duration may not be greater than 255 characters.',

            'consent_status.string' => 'The consent status must be a valid text.',
            'consent_status.max'    => 'The consent status may not be greater than 255 characters.',
        ];
    }
}
