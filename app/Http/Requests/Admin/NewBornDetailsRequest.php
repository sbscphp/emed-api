<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class NewBornDetailsRequest extends FormRequest
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
            'birth_date' => 'required',
            'birth_time' => 'required',
            'sex' => 'required',
            'weight' => 'required',
            'medical_professionals' => 'required',
            'observation' => 'required',
            'placenta' => 'required',
            'medications_given' => 'required',
            'complications' => 'required'
        ];
    }
}
