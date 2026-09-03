<?php

namespace App\Http\Requests\Patient\Profile;

use App\Http\Requests\Patient\PatientRequest;

/**
 * Adding or editing a medical condition.
 *
 * The diagnosis date is a month and a year on the screen, so anything Carbon can
 * read as a date is accepted — "2018-08", "Aug 2018", "2018-08-14" — and the
 * service stores the first of that month.
 */
class MedicalConditionRequest extends PatientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'diagnosed_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
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
            'name.required' => 'Enter the name of the condition.',
            'diagnosed_at.date' => 'Enter the month you were diagnosed, for example Aug 2018.',
            'diagnosed_at.before_or_equal' => 'A diagnosis date cannot be in the future.',
            'notes.max' => 'Please keep your notes under 2000 characters.',
        ];
    }
}
