<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditPharmacyServiceRequest extends FormRequest
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
            "id" => "required|numeric|exists:pharmacy_services,id",
            'active_ingredent' => 'nullable|string|max:255',
            'price' => 'nullable|integer|min:0',
            'name' => 'nullable|string|max:255',
        ];
    }
}
