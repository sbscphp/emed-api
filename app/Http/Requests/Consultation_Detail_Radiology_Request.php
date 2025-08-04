<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Consultation_Detail_Radiology_Request extends FormRequest
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
            'laborartory_department' => 'nullable|string',
            // in:x-ray,scan,special scan,ultrasound
            'laborartory_test' => 'nullable|string',
            // in:abdomen supine/erect,both elbow,both elbow joint ap/lat,both ankle ap./lat,knee ap./lat,cervical spine lat only
            'other_laborartory_test' => 'nullable|string|max:255',
            'ordered_test' => 'nullable|string|max:255',
        ];
    }
}
