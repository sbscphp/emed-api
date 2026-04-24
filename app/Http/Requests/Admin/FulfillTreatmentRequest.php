<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FulfillTreatmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'treatment_id'         => 'required|exists:tenant.patient_visit_treatment,id',
            'dispensing_date'      => 'required|date',
            'quantity_dispensed'   => 'required|integer|min:1',
            // 'batch_number'         => 'required|string|max:255',
            // 'expiry_date'          => 'required|date|after_or_equal:dispensing_date',
        ];
    }
}
