<?php

namespace App\Http\Requests\Patient\Admission;

use App\Helpers\GeneralHelper;
use App\Http\Requests\Patient\PatientRequest;
use App\Models\AdmittedPatient;
use Illuminate\Validation\Rule;

/**
 * The filters above the patient app's admissions list: a search box, the status
 * chips, and the same date filter every other list in the app uses.
 */
class AdmissionIndexRequest extends PatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(AdmittedPatient::STATUSES)],
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
            'status.in' => 'That admission status is not one of the supported options.',
            'period.in' => 'That date filter is not one of the supported options.',
            'start_date.required_with' => 'Choose the date the range starts from.',
            'end_date.required_with' => 'Choose the date the range runs to.',
            'end_date.after_or_equal' => 'The end of the range cannot fall before its start.',
        ];
    }
}
