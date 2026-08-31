<?php

namespace App\Http\Requests\Admission;

use App\Models\AdmittedPatient;
use Illuminate\Validation\Rule;

/**
 * The "New Admission" form.
 *
 * Serves both entry points: an admission already initiated from a consultation
 * (admission_id supplied, the record is completed) and one raised directly from
 * the admissions page (no admission_id, the record is created here).
 */
class StoreAdmissionRequest extends AdmissionFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'admission_id' => ['nullable', 'integer', 'exists:tenant.admitted_patients,id'],
            'patient_id' => ['required_without:admission_id', 'nullable', 'integer', 'exists:tenant.patients,id'],
            'visit_id' => ['required_without:admission_id', 'nullable', 'integer', 'exists:tenant.patient_visits,id'],

            'admission_date' => ['nullable', 'date'],
            'admission_time' => ['nullable', self::TIME_FORMATS],
            'admission_type' => ['required', 'string', Rule::in(AdmittedPatient::TYPES)],
            'referred_by' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'department_id' => ['nullable', 'integer', 'exists:tenant.departments,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:landlord.users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'ward_id' => ['required', 'integer', 'exists:tenant.wards,id'],
            'bed_id' => ['nullable', 'integer', 'exists:tenant.beds,id'],
            'bed_space' => ['nullable', 'string', 'max:100'],

            'payer_type' => ['nullable', 'string', 'max:100'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['nullable', 'string', Rule::in(AdmittedPatient::PAYMENT_STATUSES)],
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
            'admission_id.exists' => 'The selected admission does not exist.',
            'patient_id.required_without' => 'Please select a patient for the admission.',
            'patient_id.exists' => 'The selected patient does not exist.',
            'visit_id.required_without' => 'Please select the visit this admission belongs to.',
            'visit_id.exists' => 'The selected patient visit does not exist.',
            'admission_type.required' => 'Admission type is required.',
            'admission_type.in' => 'Admission type must be one of: ' . implode(', ', AdmittedPatient::TYPES) . '.',
            'admission_time.date_format' => 'Admission time must be a valid time, e.g. 09:15 or 09:15 AM.',
            'department_id.exists' => 'The selected admitting department does not exist.',
            'doctor_id.exists' => 'The selected doctor does not exist.',
            'ward_id.required' => 'Please select a ward for the admission.',
            'ward_id.exists' => 'The selected ward does not exist.',
            'bed_id.exists' => 'The selected bed space does not exist.',
            'payment_status.in' => 'Payment status must be one of: ' . implode(', ', AdmittedPatient::PAYMENT_STATUSES) . '.',
        ];
    }
}
