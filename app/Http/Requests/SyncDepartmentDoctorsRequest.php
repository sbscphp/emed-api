<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Assigning the doctors that consult in a department.
 *
 * The screen behind it is a checklist, so it posts the whole list it ended up
 * with and an empty array is a valid answer: it clears the department. Only that
 * the ids are ids is checked here — that each one is a consultant of this
 * hospital is settled by the service, which is where the tenant is known.
 */
class SyncDepartmentDoctorsRequest extends FormRequest
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
            'doctor_ids' => ['present', 'array'],
            'doctor_ids.*' => ['integer', 'exists:landlord.users,id'],
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
            'doctor_ids.present' => 'Send the list of doctors that should consult in this department.',
            'doctor_ids.array' => 'The list of doctors must be an array of user ids.',
            'doctor_ids.*.exists' => 'One of the selected doctors does not exist.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $firstError = $validator->errors()->first();

        throw new HttpResponseException(
            response()->json(
                [
                    'error' => true,
                    'message' => $firstError,
                    'data' => null,
                ],
                422
            )
        );
    }
}
