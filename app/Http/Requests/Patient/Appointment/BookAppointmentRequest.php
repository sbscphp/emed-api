<?php

namespace App\Http\Requests\Patient\Appointment;

use App\Http\Requests\Patient\PatientRequest;
use App\Models\Appointment;
use Illuminate\Validation\Rule;

/**
 * The payload the "Review your appointment" screen posts when the patient taps
 * "Confirm Appointment".
 *
 * Only what the patient actually chose is accepted. The status, the appointment
 * number, the patient record and who did the booking are all decided by the
 * service, so nothing here can talk the API into booking for somebody else or
 * into a state the flow never offers.
 */
class BookAppointmentRequest extends PatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'consultation_type' => ['required', 'string', Rule::in(array_keys(Appointment::CONSULTATION_TYPES))],
            'department_id' => ['required', 'integer', 'exists:tenant.departments,id'],
            'doctor_id' => ['required', 'integer', 'exists:landlord.users,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i,H:i:s,h:i A,h:iA'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'appointment_type' => ['sometimes', 'string', Rule::in(Appointment::TYPES)],

            // Only a video consultation is held anywhere, so the platform is
            // required for one and ignored for the other.
            'meeting_platform' => [
                Rule::requiredIf(fn() => $this->input('consultation_type') === 'tele'),
                'nullable',
                'string',
                Rule::in($this->meetingPlatforms()),
            ],
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
            'consultation_type.required' => 'Select the type of appointment you want to book.',
            'consultation_type.in' => 'Choose either an in person consultation or a tele consultation.',
            'department_id.required' => 'Select the department you want to be seen in.',
            'department_id.exists' => 'The selected department is not available at this hospital.',
            'doctor_id.required' => 'Select the doctor you would like to see.',
            'doctor_id.exists' => 'The selected doctor is not available at this hospital.',
            'date.required' => 'Select the date of your appointment.',
            'date.after_or_equal' => 'Appointments cannot be booked in the past.',
            'time.required' => 'Select a time slot for your appointment.',
            'time.date_format' => 'Select a valid time slot, for example 09:00 AM.',
            'meeting_platform.required' => 'Choose the platform your video consultation will be held on.',
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
