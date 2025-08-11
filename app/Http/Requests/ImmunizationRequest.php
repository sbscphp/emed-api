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
            "patient_id" => "required|exists:tenant.patients,id",
            "schedule_a_follow_up" => "nullable|boolean",
            "schedule_a_follow_up_date" => "required_if:schedule_a_follow_up,true|nullable|date",
            "referral" => "nullable|boolean",
            "referral_detail" => "required_if:referral,true|nullable|string",
            "immunization_type" => "nullable|string"
        ];
    }
}
