<?php

namespace App\Http\Requests\Patient\Billing;

use App\Http\Requests\Patient\PatientRequest;
use App\Models\PaymentSupportRequest;
use Illuminate\Validation\Rule;

/**
 * The list of support links a patient has raised.
 *
 * Paginated on request rather than always, so the dashboard can ask for the
 * first few and the "Request payment support" screen can walk the full history.
 */
class SupportIndexRequest extends PatientRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in([
                PaymentSupportRequest::ACTIVE,
                PaymentSupportRequest::COMPLETED,
                PaymentSupportRequest::EXPIRED,
                PaymentSupportRequest::CANCELLED,
            ])],
            'paginate' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'That is not a status a support request can be in.',
        ];
    }
}
