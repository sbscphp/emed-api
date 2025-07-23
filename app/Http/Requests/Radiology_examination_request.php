<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Radiology_examination_request extends FormRequest
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
            'patient_id' => "nullable|numeric|exists:patients,id",
            'test_name' => "nullable|string",
            'doctor_id' => 'required|integer|exists:tenant.users,id',
            "all_exam" => "required|json"
        ];
    }
}
