<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationLaborartoryRequest extends FormRequest
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
            'patient_id' => 'required|exists:tenant.patients,id',
            'visit_id' => 'required|exists:tenant.patient_visits,id',
            'consultation_id' => 'required|exists:tenant.patient_visit_consultation,id',

            // test must be an array
            'test' => 'required|array|min:1',

            // each test item should have an id, name and department
            'test.*.test_id' => 'required|exists:tenant.lab_services,id',
            'test.*.test_name' => 'required|string',
            // 'test.*.department' => 'required|string',
        ];
    }
}
