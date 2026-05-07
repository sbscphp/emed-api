<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TriageRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Adjust if needed for permissions
    }

    public function rules()
    {
        return [
            'visit_id' => 'required',
            'patient_id' => 'required',
            'blood_pressure' => ['nullable', 'array', 'min:1'],
            'blood_pressure.*.systolic' => ['nullable', 'integer', 'min:1'],
            'blood_pressure.*.diastolic' => ['nullable', 'integer', 'min:1'],
            'pulse_bpm' => 'nullable|integer',
            'sugar_level' => 'nullable|numeric',
            'weight_kg' => 'nullable|numeric',
            'temperature' => 'nullable|numeric',
            'severity' => 'nullable|integer|min:1|max:5',
            'sp02' => 'nullable',
            'height' => 'nullable',
        ];
    }
}
