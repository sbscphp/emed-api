<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'generic_name' => 'required|string|max:255',
            'brand_name' => 'required|string|max:255',
            'medicine_name' => 'required|string|max:255',
            'medicine_type' => 'required|string|max:255',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'reg_no' => [
                'required',
                'string',
                Rule::unique('tenant.medications', 'reg_no'),
            ],
            'manufacturer' => 'required|string|max:255',
            'medicine_status'  => 'nullable|in:available,about to expire,out of stock,expired',
            'pharmacy_id' => 'nullable|exists:tenant.pharmacies,id',
        ];
    }

    public function messages(): array
    {
        return [
            'generic_name.required' => 'Generic name is required.',
            'brand_name.required' => 'Brand name is required.',
            'medicine_name.required' => 'Medicine name is required.',
            'medicine_type.required' => 'Medicine type is required.',
            'cost_price.required' => 'Cost price is required.',
            'cost_price.numeric' => 'Cost price must be a number.',
            'selling_price.required' => 'Selling price is required.',
            'selling_price.numeric' => 'Selling price must be a number.',
            'reg_no.required' => 'Registration number is required.',
            'reg_no.unique' => 'The registration number already exists.',
            'manufacturer.required' => 'Manufacturer is required.',
            'medicine_status.in' => 'Medicine status must be one of: available, about to expire, out of stock, expired.',
            'pharmacy_id.required' => 'Pharmacy ID is required.',
            'pharmacy_id.exists' => 'The selected pharmacy does not exist.',
        ];
    }
}
