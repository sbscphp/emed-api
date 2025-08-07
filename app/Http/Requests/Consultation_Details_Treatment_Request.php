<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Consultation_Details_Treatment_Request extends FormRequest
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
            // 'patient_id' => 'nullable|exists:tenant.patients,id',
            // 'patient_visits_id' => 'nullable|exists:tenant.patient_visits,id',
            // 'select_drug' => 'nullable|string',
            // 'qualifier' => 'nullable|string',
            // 'dosage' => 'nullable|string',
            // 'weight' => 'nullable|string',
            // 'adherence_period' => 'nullable|string|max:255',
            // 'duration' => 'nullable|string|max:255',
            // 'route' => 'nullable|string',
            // 'remark' => 'nullable|string|max:1000',
            'treatments' => 'required|array',
            'treatments.*.patient_id' => 'nullable|exists:tenant.patients,id',
            'treatments.*.patient_visits_id' => 'nullable|exists:tenant.patient_visits,id',
            'treatments.*.select_drug' => 'nullable|string',
            'treatments.*.qualifier' => 'nullable|string',
            'treatments.*.dosage' => 'nullable|string',
            'treatments.*.weight' => 'nullable|string',
            'treatments.*.adherence_period' => 'nullable|string|max:255',
            'treatments.*.duration' => 'nullable|string|max:255',
            'treatments.*.route' => 'nullable|string',
            'treatments.*.remark' => 'nullable|string|max:1000',
        ];
    }
}
