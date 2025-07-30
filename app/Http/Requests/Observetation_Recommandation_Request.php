<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Observetation_Recommandation_Request extends FormRequest
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
            'patient_name' => 'nullable|string',
            'patient_card_name' => 'nullable|integer',
            'date_of_session' => "nullable|date",
            'time_of_session' => 'nullable|string',
            'counsellor_name' => 'nullable|string',
            'counsellor_id' => 'nullable|string',
            'session_type' => 'nullable|string',
            'means_of_session' => 'nullable|string',
            'schedule_a_follow' => "nullable|boolean",
            'schedule_date' => "nullable|date",
            'referral' => "nullable|boolean",
            'details' => 'nullable|string'
        ];
    }
}
