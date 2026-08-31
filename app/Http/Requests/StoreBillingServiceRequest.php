<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreBillingServiceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // Left out, the code is derived from the name by the service.
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('tenant.billing_services', 'code')->where(function ($query) {
                    $query->where('tenant_id', $this->header('X-Tenant-ID'))->whereNull('deleted_at');
                }),
            ],
            'category' => ['nullable', 'string', 'max:100'],
            'service_unit_id' => ['nullable', 'integer', 'exists:tenant.service_units,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Service name is required.',
            'code.unique' => 'A billing service with this code already exists.',
            'service_unit_id.exists' => 'The selected service unit does not exist.',
            'price.required' => 'Service price is required.',
            'price.numeric' => 'Service price must be a valid number.',
            'price.min' => 'Service price must be a positive number.',
            'status.boolean' => 'Status must be true or false.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  Validator  $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json(
                [
                    'error' => true,
                    'message' => $validator->errors()->first(),
                    'data' => null,
                ],
                422
            )
        );
    }
}
