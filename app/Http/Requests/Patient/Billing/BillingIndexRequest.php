<?php

namespace App\Http\Requests\Patient\Billing;

use App\Helpers\GeneralHelper;
use App\Http\Requests\Patient\PatientRequest;
use App\Services\Patient\Billing\PatientBillingService;
use Illuminate\Validation\Rule;

/**
 * The filters above the patient app's Billing list.
 *
 * The same search box and date sheet as every other list in the app, plus the
 * tab that says which half of the ledger the list is narrowed to.
 */
class BillingIndexRequest extends PatientRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', Rule::in(PatientBillingService::TABS)],
            'search_param' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'period' => ['nullable', 'string', Rule::in(GeneralHelper::DATE_PERIODS)],
            'start_date' => ['nullable', 'date', 'required_with:end_date'],
            'end_date' => ['nullable', 'date', 'required_with:start_date', 'after_or_equal:start_date'],
            'paginate' => ['nullable', 'boolean'],

            // Rows per page when paginating, and how many rows to preview when
            // not — the summary screen shows the first few of each list above
            // its "View all".
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],

            // One list, so the ordinary page cursor.
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tab.in' => 'Choose one of: ' . implode(', ', PatientBillingService::TABS) . '.',
            'period.in' => 'That date filter is not one of the supported options.',
            'end_date.after_or_equal' => 'The end of the range cannot fall before its start.',
        ];
    }
}
