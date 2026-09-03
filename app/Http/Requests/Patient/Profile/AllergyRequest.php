<?php

namespace App\Http\Requests\Patient\Profile;

use App\Http\Requests\Patient\PatientRequest;
use App\Models\PatientAllergy;
use Illuminate\Validation\Rule;

/**
 * Adding or editing an allergy.
 *
 * One request for both, because the screen is the same sheet either way. The
 * name is required when adding and optional when editing, which is what `sometimes`
 * on a PUT and a present field on a POST work out to.
 */
class AllergyRequest extends PatientRequest
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
            'type' => [$isUpdate ? 'sometimes' : 'required', 'string', Rule::in(PatientAllergy::TYPES)],
            'reaction' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
            'name.required' => 'Enter what you are allergic to.',
            'type.required' => 'Choose the type of allergy.',
            'type.in' => 'Choose one of: ' . implode(', ', PatientAllergy::TYPES) . '.',
            'reaction.max' => 'Please keep the reaction under 1000 characters.',
        ];
    }
}
