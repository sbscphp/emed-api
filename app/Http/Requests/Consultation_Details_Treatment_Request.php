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
            '*.patient_id' => 'nullable|exists:tenant.patients,id',
            '*.patient_visits_id' => 'nullable|exists:tenant.patient_visits,id',
            '*.select_drug' => 'nullable|string',
            '*.qualifier' => 'nullable|string',
            '*.dosage' => 'nullable|string',
            '*.weight' => 'nullable|string',
            '*.adherence_period' => 'nullable|string|max:255',
            '*.duration' => 'nullable|string|max:255',
            '*.route' => 'nullable|string',
            '*.remark' => 'nullable|string|max:1000',
        ];
    }
}
