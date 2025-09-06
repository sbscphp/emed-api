<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreatePharmacyRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pharmacy_id' => 'required|exists:tenant.pharmacies,id',
            'inventory_id' => 'required',
            // 'requested_by' => 'required|string|max:255',
            'requested_date' => 'required|date',
            'urgency_level' => 'required|string|in:low,medium,high',
            'product' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'quantity_requested' => 'required|integer|min:1',
            'reason_for_request' => 'nullable|string',
        ];
    }
}
