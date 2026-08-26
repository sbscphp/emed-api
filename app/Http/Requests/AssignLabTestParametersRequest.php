<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignLabTestParametersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parameters' => 'required|array|min:1',
            'parameters.*.name' => 'required|string|max:255',
            'parameters.*.code' => 'nullable|string|max:255',
            'parameters.*.unit' => 'nullable|string|max:255',
            'parameters.*.reference_range' => 'nullable|string|max:255',
            'parameters.*.input_type' => 'nullable|string|max:50',
            'parameters.*.display_order' => 'nullable|integer|min:0',
            'parameters.*.is_required' => 'nullable|boolean',
            'parameters.*.status' => 'nullable|boolean',
        ];
    }
}
