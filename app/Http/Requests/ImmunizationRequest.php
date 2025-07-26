<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImmunizationRequest extends FormRequest
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

            // "patient_id" => "requiredin|exists:tenant.patients,id",
            // "schedule_a_follow_up" => "nullable|boolean",
            // "schedule_a_follow_up_date" => "nullable|date",
            // "referral" => "nullable|boolean",
            // "immunization_type" => "nullable|in:COVID-19 Vaccine,Hepatitis B Vaccine,Polio Vaccine,Measles Vaccine,BCG (Tuberculosis Vaccine)",
            // "referral_detail" => "nullable|string"

            "patient_id" => "required|exists:tenant.patients,id",
            "schedule_a_follow_up" => "nullable|boolean",
            "schedule_a_follow_up_date" => "nullable|date",
            "referral" => "nullable|boolean",
            "immunization_type" => "nullable|in:COVID-19 Vaccine,Hepatitis B Vaccine,Polio Vaccine,Measles Vaccine,BCG (Tuberculosis Vaccine)",
            "referral_detail" => "nullable|string"
        ];
    }
}
