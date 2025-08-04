<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Consultation__Details__Laborartories_Request extends FormRequest
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
            'patient_id' => 'nullable|exists:tenant.patients,id',
            'patient_visits_id' => 'nullable|exists:tenant.patient_visits,id',
            // 'laborartory_dept' => 'nullable|string',
            // 'laborartory_test' => 'nullable|string',
            'laborartory_dept' => 'nullable|string',
            // bacteriology,chemical pathology,heamatology,parasitology,anc,other test
            'laborartory_test' => 'nullable|string',
            // mircoscopic culture sensitivity,serology,microscopy,widal test,semen analysis,skin snip test
            'other_laborartory' => 'nullable|string',
            'order_test' => 'nullable|string',
        ];
    }
}
