<?php

namespace App\Http\Requests\Patient;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base request for the patient mobile app.
 *
 * Laravel's own validation response is {message, errors}, which is not the
 * {error, message, data} envelope every other endpoint answers with. The app
 * should not have to parse two shapes, so failed validation is reshaped here
 * once for the whole namespace, the way ResetPasswordRequest already does it.
 */
abstract class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Answer a validation failure in the API's standard envelope.
     *
     * The field errors are kept in `data` so the app can highlight the input
     * that failed, while `message` carries the line to show.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'error'   => true,
                'message' => $validator->errors()->first(),
                'data'    => $validator->errors()->toArray(),
            ], 422)
        );
    }
}
