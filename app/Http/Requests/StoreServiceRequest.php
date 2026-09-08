<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
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
        $tenantId = $this->header('X-Tenant-ID');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Service::class, 'name')
                    ->where(fn($query) => $query->where('tenant_id', $tenantId))
                    ->whereNull('deleted_at'),
            ],
            // Optional: a service is normally priced through its sub-services.
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
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
            'name.unique' => 'A service with this name already exists.',
            'name.max' => 'Service name may not be greater than 255 characters.',
            'price.numeric' => 'Service price must be a valid number.',
            'price.min' => 'Service price must be a positive number.',
            'status.boolean' => 'Service status must be true or false.',
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
