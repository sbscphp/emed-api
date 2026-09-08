<?php

namespace App\Http\Requests\Patient\Appointment;

use App\Http\Requests\Patient\PatientRequest;

/**
 * The range the booking calendar is asking about, which is normally the month
 * it has just been scrolled to.
 *
 * Both ends are optional: with neither, the service answers for the next month
 * from today. Whatever is asked for is clamped to today and to the booking
 * horizon by the service, so a wide range cannot be used to walk a doctor's
 * whole diary.
 */
class AvailabilityRequest extends PatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
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
            'to.after_or_equal' => 'The end of the range cannot fall before its start.',
        ];
    }
}
