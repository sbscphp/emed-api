<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RateCardItemRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        // On update (PUT/PATCH) all fields are optional so partial edits work.
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name'            => [$required, 'string', 'max:255'],
            'unit_price'      => [$required, 'numeric', 'min:0'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'service_unit_id' => ['nullable', 'exists:tenant.service_units,id'],
            'category'        => ['nullable', 'string', 'max:255'],
            'payer_type'      => ['nullable', 'string', 'max:100'],
            'status'          => ['nullable', Rule::in(['Active', 'Inactive'])],
        ];
    }
}
