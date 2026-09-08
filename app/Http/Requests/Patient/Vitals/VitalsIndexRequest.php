<?php

namespace App\Http\Requests\Patient\Vitals;

use App\Helpers\GeneralHelper;
use App\Http\Requests\Patient\PatientRequest;
use Illuminate\Validation\Rule;

/**
 * The "Filter date" sheet on the vitals list.
 *
 * Three ways in, all resolved by GeneralHelper::dateFilter:
 *
 *   - `date`, a single day;
 *   - `period`, one of the shortcuts — "7 days", "30 days", "3 months";
 *   - `start_date` and `end_date`, the From and To of the custom range, which
 *     the service reads as a "custom date" period.
 */
class VitalsIndexRequest extends PatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
            'period.in' => 'That date filter is not one of the supported options.',
            'start_date.required_with' => 'Choose the date the range starts from.',
            'end_date.required_with' => 'Choose the date the range runs to.',
            'end_date.after_or_equal' => 'The end of the range cannot fall before its start.',
        ];
    }
}
