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
            'medication_id' => 'required|exists:tenant.medications,id',
            'pharmacy_id' => 'required|exists:tenant.pharmacies,id',
            'shipment_no' => 'required|string|max:255',
            'batch_no' => 'required|string|max:255',
            'mfg_date' => 'required|date',
            'expiry_date' => 'required|date|after_or_equal:mfg_date',
            'date_of_shipment' => 'nullable|date|after_or_equal:today',
            'expected_delivery_date' => 'nullable|date|after_or_equal:date_of_shipment',
            'received_qty' => 'required|integer|min:1',
            'vendor' => 'required|string|max:255',
            'shipment_status' => 'required|in:pending,incomplete,complete,received',
        ];
    }
}
