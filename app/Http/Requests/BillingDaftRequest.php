<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingDaftRequest extends FormRequest
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
            'visit_id'         => 'required|exists:tenant.patient_visits,id|unique:tenant.billing_logs,visit_id',
            'patient_id'       => 'required|exists:tenant.patients,id',
            'patient_name' => 'required|string',
            'billing_date' => 'required|date',
            'service_type_id' => 'required|exists:tenant.services,id',
            'service_unit_id' => 'required|exists:tenant.service_units,id',
            'item_name' => 'required|string',
            'unit_price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
            // 'payment_status' => 'required|in:paid,part_paid,pending',
            'deposit_amount' => 'nullable|numeric',
            'payment_method' => 'nullable|in:bank_transfer,credit_card,cash,pos,insurance',
            'sub_total' => 'required|numeric',
            'tax_amount' => 'nullable|numeric',
            'grand_total' => 'required|numeric',
        ];
    }
}
