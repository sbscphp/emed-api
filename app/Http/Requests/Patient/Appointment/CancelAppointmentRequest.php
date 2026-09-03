<?php

namespace App\Http\Requests\Patient\Appointment;

use App\Http\Requests\Patient\PatientRequest;

/**
 * Calling an appointment off. The reason is optional — the app asks for one but
 * lets the patient skip it.
 */
class CancelAppointmentRequest extends PatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
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
            'reason.max' => 'Please keep the reason for cancelling under 1000 characters.',
        ];
    }
}
