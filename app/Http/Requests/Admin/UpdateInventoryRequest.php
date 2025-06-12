<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryRequest extends FormRequest
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
            'batch_no' => 'sometimes|required|string',
            'item_name' => 'sometimes|required|string',
            // 'medicine_type' => 'sometimes|required|string',
            'medicine_type_id' => 'required|exists:tenant.medicine_types,id',
            'quantity' => 'sometimes|required|integer|min:0',
            'reorder_level' => 'sometimes|required|integer|min:0',
            'supplier' => 'nullable|string',
            'expiry_date' => 'sometimes|required|date',
            'note' => 'nullable|string',
        ];
    }
}
