<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Radiology__Editservice_Request extends FormRequest
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
            "id" => "required|exists:tenant.radiology_services,id",
            "name" => "required|string",
            // numeric, not integer: radiology prices carry kobo, and integer
            // rejected every price that did.
            "price" => "required|numeric|min:0",
            "radiology_category_id" => "required|exists:tenant.radiology_categories,id",
        ];
    }

    public function messages(): array
    {
        return [
            'radiology_category_id.required' => 'Select the imaging category this service belongs to.',
            'radiology_category_id.exists' => 'The selected radiology category does not exist.',
        ];
    }
}
