<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ManualBillingRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        // Manual bills are standalone (patient only, no visit). On update, the
        // patient and items are optional so headers can be patched independently.
        $isCreate = $this->isMethod('post');
        $patientRule = $isCreate ? 'required' : 'sometimes';
        $itemsRule   = $isCreate ? 'required' : 'sometimes';

        return [
            'patient_id'                => [$patientRule, 'exists:tenant.patients,id'],
            'billing_date'              => ['nullable', 'date'],
            'discount'                  => ['nullable', 'numeric', 'min:0'],
            'tax_amount'                => ['nullable', 'numeric', 'min:0'],
            'notes'                     => ['nullable', 'string', 'max:2000'],
            'items'                     => [$itemsRule, 'array', 'min:1'],
            'items.*.rate_card_item_id' => ['nullable', 'exists:tenant.rate_card_items,id'],
            // A line either references a catalog item or supplies its own name + price.
            'items.*.item_name'         => ['required_without:items.*.rate_card_item_id', 'nullable', 'string', 'max:255'],
            'items.*.unit_price'        => ['required_without:items.*.rate_card_item_id', 'nullable', 'numeric', 'min:0'],
            'items.*.quantity'          => ['required', 'integer', 'min:1'],
            'items.*.service_unit_id'   => ['nullable', 'exists:tenant.service_units,id'],
        ];
    }
}
