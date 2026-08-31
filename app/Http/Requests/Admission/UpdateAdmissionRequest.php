<?php

namespace App\Http\Requests\Admission;

use App\Models\AdmittedPatient;
use Illuminate\Validation\Rule;

/**
 * Edits the clinical and financial detail of an admission that already exists.
 *
 * Ward and bed moves are handled by the transfer endpoint so the bed counts
 * stay correct; they are deliberately not editable here.
 */
class UpdateAdmissionRequest extends AdmissionFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'admission_type' => ['sometimes', 'string', Rule::in(AdmittedPatient::TYPES)],
            'admission_date' => ['sometimes', 'nullable', 'date'],
            'admission_time' => ['sometimes', 'nullable', self::TIME_FORMATS],
            'expected_admission_date' => ['sometimes', 'nullable', 'date'],
            'expected_admission_time' => ['sometimes', 'nullable', self::TIME_FORMATS],
            'referred_by' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.departments,id'],
            'doctor_id' => ['sometimes', 'nullable', 'integer', 'exists:landlord.users,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'payer_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'deposit_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', 'nullable', 'string', Rule::in(AdmittedPatient::PAYMENT_STATUSES)],
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
            'admission_type.in' => 'Admission type must be one of: ' . implode(', ', AdmittedPatient::TYPES) . '.',
            'department_id.exists' => 'The selected admitting department does not exist.',
            'doctor_id.exists' => 'The selected doctor does not exist.',
            'payment_status.in' => 'Payment status must be one of: ' . implode(', ', AdmittedPatient::PAYMENT_STATUSES) . '.',
        ];
    }
}
