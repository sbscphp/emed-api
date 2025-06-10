<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePharmacySupplyRequest extends FormRequest
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
            'pharmacy_id'       => 'required|exists:tenant.pharmacies,id',
            'product_name'      => 'required|string',
            'product_category'  => 'required|string',
            'quantity_supplied' => 'required|integer|min:1',
            'stock_level'       => 'required|string',
            'supplier_name'     => 'required|string',
            'batch_number'      => 'nullable|string',
            'supplied_date'     => 'required|date',
        ];
    }
}
