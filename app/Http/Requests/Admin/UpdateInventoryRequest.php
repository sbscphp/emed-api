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
            'medication_id' => 'required_without:item_name',
            'item_name'     => 'required_without:medication_id',
            'category' => 'nullable|string|max:255',
            'batch_no' => 'required|string',
            // 'medicine_type' => 'required|string',
            'medicine_type_id' => 'required|exists:tenant.medicine_types,id',
            'quantity' => 'required|integer|min:0',
            'reorder_level' => 'required|integer|min:0',
            'supplier' => 'nullable|string',
            'expiry_date' => 'nullable|date',
            'note' => 'nullable|string',
        ];
    }
}
