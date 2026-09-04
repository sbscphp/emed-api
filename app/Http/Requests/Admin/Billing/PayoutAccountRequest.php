<?php

namespace App\Http\Requests\Admin\Billing;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * A hospital saying which bank account its share of a patient payment settles
 * into.
 *
 * The account name is not accepted from the client. It is resolved with the bank
 * during the save, so what ends up stored is the name the bank returned rather
 * than one somebody typed — which is what makes a transposed digit fail here
 * instead of at the first settlement.
 *
 * `commission_percent` is the platform's cut and is only meaningful to whoever
 * negotiated it; it is optional, and falls back to the platform default.
 */
class PayoutAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_code' => ['required', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_code.required' => 'Choose the bank this account is held with.',
            'account_number.required' => 'Enter the account number.',
            'account_number.regex' => 'An account number is ten digits.',
            'commission_percent.max' => 'A commission cannot be more than the whole payment.',
        ];
    }

    /**
     * Answer a validation failure in the envelope the rest of the API uses.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => $validator->errors()->toArray(),
            ], 422)
        );
    }
}
