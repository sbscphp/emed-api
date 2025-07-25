<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Consultation__DetailsRequest extends FormRequest
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
            'patient_visits_id' => 'nullable|exists:tenant.patient_visits,id',
            'complaints' => 'nullable|string',
            'history_of_present_complaints' => 'nullable|string',
            'system_view' => 'nullable|string',
            'provisional_diagnosis' => 'nullable|string',
            'disease_patterns' => 'nullable|string',
            'disease_types' => 'nullable|string',
            'allergies' => 'nullable|string',
            'laboratory' => 'nullable|boolean',
            'radiology' => 'nullable|boolean',
            'both' => 'nullable|boolean',
            'schedule_a_follow_up' => 'nullable|boolean',
            'referral' => 'nullable|boolean',
            'relationship_type' => 'nullable|string',
            'chronic_lllness' => 'nullable|string',
            'genetic_disorder' => 'nullable|string',
            'age_of_onset' => 'nullable|integer',
            'causes_of_death_in_family_member' => 'nullable|string',
            'other_details' => 'nullable|string',
            'occupation' => 'nullable|string',
            'living_situation' => 'nullable|string',
            'substance_use' => 'nullable|string',
            'lifesytle_habits' => 'nullable|string',
            'sexual_history' => 'nullable|string',
            'admit_patient' => 'nullable|boolean',
        ];
    }
}
