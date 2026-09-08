<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateBillingServiceRequest extends FormRequest
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
            'service_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('tenant.services', 'id')->where(function ($query) {
                    $query->where('tenant_id', $this->header('X-Tenant-ID'))->whereNull('deleted_at');
                }),
            ],
            'department_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('tenant.departments', 'id')->where(function ($query) {
                    $query->where('tenant_uuid', $this->header('X-Tenant-ID'))->whereNull('deleted_at');
                }),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('tenant.billing_services', 'code')
                    ->ignore($this->route('id'))
                    ->where(function ($query) {
                        $query->where('tenant_id', $this->header('X-Tenant-ID'))->whereNull('deleted_at');
                    }),
            ],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'service_unit_id' => ['sometimes', 'nullable', 'integer', 'exists:tenant.service_units,id'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
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
            'service_id.required' => 'Parent service cannot be empty.',
            'service_id.exists' => 'The selected parent service does not exist.',
            'department_id.exists' => 'The selected department does not exist.',
            'name.required' => 'Sub-service name cannot be empty.',
            'code.required' => 'Service code cannot be empty.',
            'code.unique' => 'A billing service with this code already exists.',
            'service_unit_id.exists' => 'The selected service unit does not exist.',
            'price.required' => 'Service price cannot be empty.',
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
