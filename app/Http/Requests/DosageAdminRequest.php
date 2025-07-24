<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DosageAdminRequest extends FormRequest
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
            // "vaccine_name" => "nullable|string",
            // "vaccine_code" => "nullable|string",
            // "dosage" => "nullable|numeric",
            // "weight" => "nullable|string|in:Milligram,Grams,Mircogram,Mis",
            // "batch_number" => "nullable|numeric",
            // "administration_date" => "nullable|date",
            // "manufacturer" => "nullable|string",
            // "expiration_date" => "nullable|date",
            // "route_of_adminstration" => "nullable|string",
            // "manufacturer" => "nullable|string",
            // "route_of_administration" => "nullable|string",
            // "injection_site" => "nullable|string",
            // "administering_healthcare_professional" => "nullable|string"
            "patient_id" => "required|exists:tenant.patients,id",
            "vaccine_name" => "nullable|string",
            "vaccine_code" => "nullable|string",
            "dosage" => "nullable|numeric",
            "weight" => "nullable|string|in:Milligram,Grams,Microgram,Mis",
            "batch_number" => "nullable|numeric",
            "administration_date" => "nullable|date",
            "manufacturer" => "nullable|string",
            "expiration_date" => "nullable|date",
            "route_of_administration" => "nullable|string",
            "injection_site" => "nullable|string",
            "administering_healthcare_professional" => "nullable|string"
        ];
    }
}
