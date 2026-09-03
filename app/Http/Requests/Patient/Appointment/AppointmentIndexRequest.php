<?php

namespace App\Http\Requests\Patient\Appointment;

use App\Helpers\GeneralHelper;
use App\Http\Requests\Patient\PatientRequest;
use App\Models\Appointment;
use App\Services\Patient\Appointment\PatientAppointmentService;
use Illuminate\Validation\Rule;

/**
 * The filters above the Upcoming / Past / Cancelled list.
 *
 * Dates arrive the same three ways as everywhere else in the app, all resolved
 * by GeneralHelper::dateFilter:
 *
 *   - `date`, a single day off the calendar;
 *   - `period`, one of the named shortcuts;
 *   - `start_date` and `end_date`, which the service reads as a "custom date"
 *     period.
 */
class AppointmentIndexRequest extends PatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', Rule::in(PatientAppointmentService::TABS)],
            'status' => ['nullable', 'string', Rule::in(Appointment::STATUSES)],
            'visit_type' => ['nullable', 'string', Rule::in(Appointment::VISIT_TYPES)],
            'department_id' => ['nullable', 'integer'],
            'search_param' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'period' => ['nullable', 'string', Rule::in(GeneralHelper::DATE_PERIODS)],
            'start_date' => ['nullable', 'date', 'required_with:end_date'],
            'end_date' => ['nullable', 'date', 'required_with:start_date', 'after_or_equal:start_date'],
            'paginate' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
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
            'tab.in' => 'Choose one of: ' . implode(', ', PatientAppointmentService::TABS) . '.',
            'status.in' => 'That appointment status is not one of the supported options.',
            'visit_type.in' => 'That appointment type is not one of the supported options.',
            'period.in' => 'That date filter is not one of the supported options.',
            'start_date.required_with' => 'Choose the date the range starts from.',
            'end_date.required_with' => 'Choose the date the range runs to.',
            'end_date.after_or_equal' => 'The end of the range cannot fall before its start.',
        ];
    }
}
