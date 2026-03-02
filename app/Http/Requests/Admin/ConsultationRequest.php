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
            'complaints' => 'required',
            'history_of_present_complaints' => 'required|string',
            'system_view' => 'required|string',
            'provisional_diagnosis' => 'required|string',
            'disease_patterns' => 'required|string',
            'disease_types' => 'required|string',
            'allergies' => 'required',
            'final_diog' => 'required|string',
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
            'diagnosis' => 'required|string',
            'note' => 'required',
            'admit_patient' => 'required|boolean',
        ];
    }
}
