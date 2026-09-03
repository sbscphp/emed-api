<?php

namespace App\Http\Requests\Patient\Appointment;

use App\Http\Requests\Patient\PatientRequest;
use App\Models\Appointment;
use Illuminate\Validation\Rule;

/**
 * Editing an appointment the patient already has.
 *
 * Every field is optional, because the same endpoint serves three small
 * screens: the reschedule sheet, the reason field and the meeting platform
 * switcher. What may not be touched at all is the status — cancelling has its
 * own endpoint, and completing one is the hospital's business.
 */
class UpdateAppointmentRequest extends PatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'consultation_type' => ['sometimes', 'required', 'string', Rule::in(array_keys(Appointment::CONSULTATION_TYPES))],
            'department_id' => ['sometimes', 'required', 'integer', 'exists:tenant.departments,id'],
            'doctor_id' => ['sometimes', 'required', 'integer', 'exists:landlord.users,id'],
            'date' => ['sometimes', 'required', 'date', 'after_or_equal:today'],
            'time' => ['sometimes', 'required', 'date_format:H:i,H:i:s,h:i A,h:iA'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'meeting_platform' => ['sometimes', 'nullable', 'string', Rule::in($this->meetingPlatforms())],
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
            'consultation_type.in' => 'Choose either an in person consultation or a tele consultation.',
            'department_id.exists' => 'The selected department is not available at this hospital.',
            'doctor_id.exists' => 'The selected doctor is not available at this hospital.',
            'date.after_or_equal' => 'Appointments cannot be moved into the past.',
            'time.date_format' => 'Select a valid time slot, for example 09:00 AM.',
            'meeting_platform.in' => 'That meeting platform is not one of the supported options.',
            'reason.max' => 'Please keep the reason for your visit under 5000 characters.',
        ];
    }

    /**
     * The platform names offered by the app, which is what is stored.
     *
     * @return array<int, string>
     */
    protected function meetingPlatforms(): array
    {
        return array_column((array) config('patient_app.appointments.meeting_platforms', []), 'name');
    }
}
