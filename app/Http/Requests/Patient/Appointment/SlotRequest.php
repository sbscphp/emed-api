<?php

namespace App\Http\Requests\Patient\Appointment;

use App\Http\Requests\Patient\PatientRequest;

/**
 * The day the time slot picker is showing.
 *
 * `appointment_id` is sent while rescheduling: the slot the appointment already
 * holds is its own, so it is offered back rather than shown as taken.
 */
class SlotRequest extends PatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'appointment_id' => ['nullable', 'integer'],
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
            'date.required' => 'Select a date to see the times available on it.',
            'date.date' => 'Select a valid date.',
        ];
    }
}
