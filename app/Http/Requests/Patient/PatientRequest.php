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
     * Refuse an update that carried nothing to update.
     *
     * Every edit form here validates its fields with `sometimes`, so a request
     * whose body never arrived passes validation cleanly, changes nothing, and
     * answers 200 with the record exactly as it was. That is how a client
     * sending a body the server could not read — a multipart PUT, most often —
     * gets told its edit succeeded while the old value stares back at it.
     *
     * A request that genuinely means "change nothing" has no reason to be sent,
     * so an empty body is treated as the mistake it almost always is.
     *
     * Opted into per request class rather than applied to all of them, because a
     * filter or a listing legitimately arrives empty.
     */
    protected function rejectEmptyUpdates(): bool
    {
        return false;
    }

    /**
     * @return array<int, string>  the fields that count as "something to update"
     */
    protected function updatableFields(): array
    {
        return array_keys($this->rules());
    }

    protected function prepareForValidation(): void
    {
        if (!$this->rejectEmptyUpdates() || !$this->isMethod('PUT') && !$this->isMethod('PATCH')) {
            return;
        }

        $sent = array_intersect_key($this->all(), array_flip($this->updatableFields()));

        if (!empty($sent)) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'error' => true,
            'message' => 'Send at least one field to update. If you are using form-data, send JSON instead.',
            'data' => [],
        ], 422));
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
