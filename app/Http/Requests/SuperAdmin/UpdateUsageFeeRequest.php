<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUsageFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $usageFeeId = $this->route('id');

        return [
            'name' => 'required|string|max:255|unique:landlord.usage_fees,name,' . $usageFeeId,
            'is_general_visit' => 'required|boolean',
            'is_unique_visit' => 'required|boolean',
            'amount' => 'nullable|numeric|min:0',
            'cycles' => 'nullable|array',
            'cycles.monthly' => 'nullable|numeric|min:0',
            'cycles.quarterly' => 'nullable|numeric|min:0',
            'cycles.yearly' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:Active,Inactive',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->boolean('is_general_visit') && !$this->boolean('is_unique_visit')) {
                $validator->errors()->add('type', 'At least one usage fee type must be selected.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Usage fee name is required.',
            'name.string' => 'Usage fee name must be a string.',
            'name.max' => 'Usage fee name must not exceed 255 characters.',
            'name.unique' => 'A usage fee with that name already exists.',
            'is_general_visit.required' => 'The general visit flag is required.',
            'is_general_visit.boolean' => 'The general visit flag must be true or false.',
            'is_unique_visit.required' => 'The unique visit flag is required.',
            'is_unique_visit.boolean' => 'The unique visit flag must be true or false.',
            'amount.numeric' => 'Usage fee amount must be a number.',
            'amount.min' => 'Usage fee amount must be at least 0.',
            'cycles.array' => 'Cycles must be an object with cycle keys.',
            'cycles.monthly.numeric' => 'Monthly cycle amount must be a number.',
            'cycles.quarterly.numeric' => 'Quarterly cycle amount must be a number.',
            'cycles.yearly.numeric' => 'Yearly cycle amount must be a number.',
            'status.required' => 'Usage fee status is required.',
            'status.in' => 'Usage fee status must be either Active or Inactive.',
        ];
    }
}
