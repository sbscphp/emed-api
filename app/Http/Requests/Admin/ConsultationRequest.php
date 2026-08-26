<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationRequest extends FormRequest
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
            'patient_id' => 'nullable|exists:tenant.patients,id',
            'visit_id' => 'nullable|numeric|exists:tenant.patient_visits,id',
            'complaints' => 'nullable',
            'history_of_present_complaints' => 'nullable|string',
            'system_view' => 'nullable|string',
            'provisional_diagnosis' => 'nullable|string',
            'disease_patterns' => 'nullable|string',
            'disease_types' => 'nullable|string',
            'allergies' => 'nullable',
            'final_diog' => 'nullable|string',
            // 'relationship_type' => 'required|string',
            // 'chronic_lllness' => 'required|string',
            // 'genetic_disorder' => 'required|string',
            // 'age_of_onset' => 'required|string',
            // 'causes_of_death_in_family_member' => 'required|string',
            // 'other_details' => 'required|string',
            // 'occupation' => 'required|string',
            // 'living_situation' => 'required|string',
            // 'substance_use' => 'required|string',
            // 'lifesytle_habits' => 'required|string',
            // 'sexual_history' => 'required|string',
            'diagnosis' => 'nullable|string',
            'note' => 'nullable',
            'admit_patient' => 'required|boolean',
        ];
    }
}
