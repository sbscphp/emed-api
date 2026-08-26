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
            'active_ingredient' => 'nullable',
            'brand_name' => 'nullable',
            'received_qty' => 'nullable',
            'order_placed_by' => 'nullable',
            'mfg_date' => 'nullable',
            'batch_no' => 'nullable',
            'expiry_date' => 'nullable',
            'price' => 'nullable',
            'vendor_id' => 'required|numeric|exists:tenant.vendors,id',
            'delivery_location' => 'nullable',
            'date_of_shipment' => 'nullable',
            'expected_delivery_date' => 'nullable',
            'courier_service' => 'nullable',
            'tracking_number' => 'nullable',
            'current_location' => 'nullable',
            'dispatched_date' => 'nullable',
            'delivery_note' => 'nullable',
            'support_doc' => 'nullable',
            'support_file' => 'nullable'
        ];
    }
}
