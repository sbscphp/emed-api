<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabParameterRequest extends FormRequest
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
            'service_category_id' => 'required|exists:tenant.service_categories,id',
            'name' => 'required|string',
            'code' => 'nullable|string',
            'unit' => 'nullable|string',
            'reference_range' => 'nullable|string',
            'input_type' => 'nullable|string',
            'display_order' => 'nullable|integer|min:0',
            'is_required' => 'nullable|boolean',
            'status' => 'nullable|boolean',
        ];
    }
}
