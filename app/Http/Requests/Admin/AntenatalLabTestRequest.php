<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AntenatalLabTestRequest extends FormRequest
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
            'vdrl' => 'required',
            'hcv' => 'required',
            'genotype' => 'required',
            'hbsag' => 'required',
            'hiv' => 'required',
            'blood_group' => 'required',
            'hbgd' => 'required',
            'pcv' => 'required',
            'cvs' => 'required',
            'rs' => 'required',
            'spleen' => 'required',
            'liver' => 'required'
        ];
    }
}
