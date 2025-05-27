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
            'blood_pressure' => ['required', 'array'],
            'blood_pressure.systolic' => ['required', 'integer', 'min:1'],
            'blood_pressure.diastolic' => ['required', 'integer', 'min:1'],
            'pulse_bpm' => 'required|integer',
            'sugar_level' => 'required|numeric',
            'weight_kg' => 'required|numeric',
            'temperature' => 'required|numeric',
            'severity' => 'required|integer|min:1|max:5',
        ];
    }
}
