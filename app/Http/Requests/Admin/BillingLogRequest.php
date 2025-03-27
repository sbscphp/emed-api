<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BillingLogRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'patient_id'       => 'required|exists:tenant.patients,id',
            'patient_name' => 'required|string',
            'billing_date' => 'required|date',
            'service_type_id' => 'required|exists:tenant.services,id',
            'service_unit_id' => 'required|exists:tenant.service_units,id',
            'item_name' => 'required|string',
            'unit_price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
            'payment_status' => 'required|in:paid,part_paid,pending',
            'deposit_amount' => 'nullable|numeric',
            'payment_method' => 'nullable|in:bank_transfer,credit_card,cash,pos,insurance',
            'sub_total' => 'required|numeric',
            'tax_amount' => 'nullable|numeric',
            'grand_total' => 'required|numeric',
        ];
    }
}
