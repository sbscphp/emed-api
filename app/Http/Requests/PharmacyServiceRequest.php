<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PharmacyServiceRequest extends FormRequest
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
            //'service_unit_id' => 'nullable|exists:service_units,id',
            'active_ingredent' => 'nullable|string|max:255',
            'price' => 'nullable|integer|min:0',
            'name' => 'nullable|string|max:255',
        ];
    }
}
