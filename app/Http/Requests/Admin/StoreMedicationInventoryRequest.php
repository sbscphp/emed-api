<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicationInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => 'required|numeric|exists:tenant.vendors,id',
            'medication_id' => 'required_without:product_name',
            'product_name' => 'required_without:medication_id',
            'pharmacy_id' => 'required|numeric|exists:tenant.pharmacies,id',
            'shipment_no' => 'nullable|string|max:255',
            'batch_no' => 'required|string|max:255',
            'mfg_date' => 'required|date',
            'expiry_date' => 'required|date|after_or_equal:mfg_date',
            'date_of_shipment' => 'nullable|date|after_or_equal:today',
            'expected_delivery_date' => 'nullable|date|after_or_equal:date_of_shipment',
            'received_qty' => 'nullable|integer|min:1',
            'shipment_status' => 'required|in:pending,incomplete,complete,received',
            'active_ingredient' => 'nullable|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
        ];
    }
}
