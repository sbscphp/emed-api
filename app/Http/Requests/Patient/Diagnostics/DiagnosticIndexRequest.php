<?php

namespace App\Http\Requests\Patient\Diagnostics;

use App\Helpers\GeneralHelper;
use App\Http\Requests\Patient\PatientRequest;
use App\Services\Patient\Diagnostics\DiagnosticResultService;
use Illuminate\Validation\Rule;

/**
 * The filters above the laboratory and radiology lists.
 *
 * One request for both, because the two screens are the same screen: a search
 * box, a date button and the All / Released / Pending tabs. Dates arrive the
 * same three ways as everywhere else in the app and are resolved by
 * GeneralHelper::dateFilter.
 */
class DiagnosticIndexRequest extends PatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', Rule::in(DiagnosticResultService::TABS)],
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
            'tab.in' => 'Choose one of: ' . implode(', ', DiagnosticResultService::TABS) . '.',
            'period.in' => 'That date filter is not one of the supported options.',
            'start_date.required_with' => 'Choose the date the range starts from.',
            'end_date.required_with' => 'Choose the date the range runs to.',
            'end_date.after_or_equal' => 'The end of the range cannot fall before its start.',
        ];
    }
}
