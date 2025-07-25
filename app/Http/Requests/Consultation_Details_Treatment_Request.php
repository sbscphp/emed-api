<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Consultation_Details_Treatment_Request extends FormRequest
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
            'select_drug' => 'nullable|string|in:paracetamol-tablets,paracetamol-injection,paracetamol syr-syrup,paracetamol-infusion,paramark-infusion',
            'qualifier' => 'nullable|string|in:tablets,capsule,injection,infusion,creams,syrup',
            'dosage' => 'nullable|in:once daily,twice daily,three time daily,four time daily,nocte',
            'weight' => 'nullable|in:Mg-Milligram,Gm-Grams,Mcg-Mircograms,Mis-Mis',
            'adherence_period' => 'nullable|string|max:255',
            'duration' => 'nullable|string|max:255',
            'route' => 'nullable|string|in:oral,mouth,intra-dermal,intra-muscular,sublingual,tropical',
            'remark' => 'nullable|string|max:1000',
        ];
    }
}
