<?php

namespace App\Http\Requests\Admission;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Shared behaviour for the admission form requests: every failure is returned
 * in the envelope the rest of the API answers with.
 */
abstract class AdmissionFormRequest extends FormRequest
{
    /**
     * The time formats the admission forms accept.
     */
    protected const TIME_FORMATS = 'date_format:H:i,H:i:s,h:i A,h:iA,h:i a,h:ia';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
