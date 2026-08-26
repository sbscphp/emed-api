<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AntenatalRequest extends FormRequest
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
            'patient_id' => 'required',
            'visit_id' => 'required',
            'booking_date' => 'required',
            'last_period_date' => 'required',
            'expected_delivery_date' => 'required',
            'pregnancy_duration' => 'required',
            'bleeding' => 'required',
            'urinary_symptoms' => 'required',
            'vaginal_discharge' => 'required',
            'other_symptoms' => 'required',
            'constipation' => 'required',
            'headache' => 'required',
            'vomiting' => 'required',
            'oedema' => 'required',
            'fmf' => 'required',
            'height' => 'required',
            'weight' => 'required',
            'skin' => 'required',
            'general_conditions' => 'required',
            'malnutrition' => 'required',
            'clinical_anaemia' => 'required',
            'liver' => 'required',
            'spleen' => 'required',
            'breast' => 'required',
            'other_abnormalities' => 'required',
            'vaginal_examination' => 'required',
            'tetanus_toxoid' => 'required'
        ];
    }
}
