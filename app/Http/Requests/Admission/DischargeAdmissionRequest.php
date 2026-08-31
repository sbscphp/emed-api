<?php

namespace App\Http\Requests\Admission;

class DischargeAdmissionRequest extends AdmissionFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'admission_id' => ['required', 'integer', 'exists:tenant.admitted_patients,id'],
            'date_discharged' => ['nullable', 'date'],
            'discharge_time' => ['nullable', self::TIME_FORMATS],
            'discharge_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'admission_id.required' => 'Please select the admission to discharge.',
            'admission_id.exists' => 'The selected admission does not exist.',
            'discharge_time.date_format' => 'Discharge time must be a valid time, e.g. 10:00 or 10:00 AM.',
        ];
    }
}
