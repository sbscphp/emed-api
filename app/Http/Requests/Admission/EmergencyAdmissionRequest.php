<?php

namespace App\Http\Requests\Admission;

use App\Models\AdmittedPatient;
use Illuminate\Validation\Rule;

/**
 * The "Emergency Admission" form.
 *
 * The patient may already be registered (patient_id) or may be walked in and
 * registered on the spot from the minimal identity fields below. When no visit
 * is supplied one is opened for the admission.
 */
class EmergencyAdmissionRequest extends AdmissionFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'integer', 'exists:tenant.patients,id'],
            'visit_id' => ['nullable', 'integer', 'exists:tenant.patient_visits,id'],
            'service_id' => ['nullable', 'integer', 'exists:tenant.services,id'],

            // Walk-in registration — only required when no existing patient is picked.
            'firstname' => ['required_without:patient_id', 'nullable', 'string', 'max:255'],
            'lastname' => ['required_without:patient_id', 'nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['required_without:patient_id', 'nullable', 'string', Rule::in(['Male', 'Female', 'Other'])],
            'homeaddress' => ['nullable', 'string', 'max:500'],
            'phoneno' => ['nullable', 'string', 'max:30'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],

            'admission_date' => ['nullable', 'date'],
            'admission_time' => ['nullable', self::TIME_FORMATS],
            'admission_type' => ['nullable', 'string', Rule::in(AdmittedPatient::TYPES)],
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
            'patient_id.exists' => 'The selected patient does not exist.',
            'visit_id.exists' => 'The selected patient visit does not exist.',
            'firstname.required_without' => 'First name is required when no existing patient is selected.',
            'lastname.required_without' => 'Last name is required when no existing patient is selected.',
            'gender.required_without' => 'Gender is required when no existing patient is selected.',
            'gender.in' => 'Gender must be Male, Female or Other.',
            'dob.before' => 'Date of birth must be a past date.',
            'admission_type.in' => 'Admission type must be one of: ' . implode(', ', AdmittedPatient::TYPES) . '.',
            'ward_id.required' => 'Please select a ward for the admission.',
            'ward_id.exists' => 'The selected ward does not exist.',
            'bed_id.exists' => 'The selected bed space does not exist.',
        ];
    }
}
