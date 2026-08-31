<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
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
            'patient_id' => ['sometimes', 'required', 'integer', 'exists:tenant.patients,id'],
            'visit_id' => ['nullable', 'integer', 'exists:tenant.patient_visits,id'],
            'appointment_type' => ['sometimes', 'required', 'string', Rule::in(Appointment::TYPES)],
            'department_id' => ['sometimes', 'required', 'integer', 'exists:tenant.departments,id'],
            'doctor_id' => ['sometimes', 'required', 'integer', 'exists:landlord.users,id'],
            'visit_type' => ['sometimes', 'required', 'string', Rule::in(Appointment::VISIT_TYPES)],
            'date' => ['sometimes', 'required', 'date'],
            'time' => ['sometimes', 'required', 'date_format:H:i,H:i:s,h:i A,h:iA'],
            'duration' => ['nullable', 'string', 'max:50'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', 'string', Rule::in(Appointment::STATUSES)],
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
            'patient_id.exists' => 'The selected patient does not exist.',
            'visit_id.exists' => 'The selected patient visit does not exist.',
            'appointment_type.in' => 'Appointment type must be one of: ' . implode(', ', Appointment::TYPES) . '.',
            'department_id.exists' => 'The selected department does not exist.',
            'doctor_id.exists' => 'The selected doctor does not exist.',
            'visit_type.in' => 'Visit type must be one of: ' . implode(', ', Appointment::VISIT_TYPES) . '.',
            'time.date_format' => 'Appointment time must be a valid time, e.g. 09:00 or 09:00 AM.',
            'status.in' => 'Status must be one of: ' . implode(', ', Appointment::STATUSES) . '.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $firstError = $validator->errors()->first();

        throw new HttpResponseException(
            response()->json(
                [
                    'error' => true,
                    'message' => $firstError,
                    'data' => null,
                ],
                422
            )
        );
    }
}
