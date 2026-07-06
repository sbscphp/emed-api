<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLabTestParameterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:255',
            'reference_range' => 'nullable|string|max:255',
            'input_type' => 'nullable|string|max:50',
            'display_order' => 'nullable|integer|min:0',
            'is_required' => 'nullable|boolean',
            'status' => 'nullable|boolean',
        ];
    }
}
