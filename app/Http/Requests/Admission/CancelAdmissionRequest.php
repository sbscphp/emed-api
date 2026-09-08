<?php

namespace App\Http\Requests\Admission;

class CancelAdmissionRequest extends AdmissionFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'admission_id' => ['required', 'integer', 'exists:tenant.admitted_patients,id'],
            'cancellation_reason' => ['required', 'string', 'max:1000'],
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
            'admission_id.required' => 'Please select the admission to cancel.',
            'admission_id.exists' => 'The selected admission does not exist.',
            'cancellation_reason.required' => 'A cancellation reason is required.',
        ];
    }
}
