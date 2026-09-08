<?php

namespace App\Http\Requests\Admission;

/**
 * Moves an admitted patient to a different ward/bed, releasing the bed they
 * currently hold and taking one in the destination ward.
 */
class TransferAdmissionRequest extends AdmissionFormRequest
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
            'ward_id' => ['required', 'integer', 'exists:tenant.wards,id'],
            'bed_id' => ['nullable', 'integer', 'exists:tenant.beds,id'],
            'bed_space' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:1000'],
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
            'admission_id.required' => 'Please select the admission to transfer.',
            'admission_id.exists' => 'The selected admission does not exist.',
            'ward_id.required' => 'Please select the ward to transfer the patient to.',
            'ward_id.exists' => 'The selected ward does not exist.',
        ];
    }
}
