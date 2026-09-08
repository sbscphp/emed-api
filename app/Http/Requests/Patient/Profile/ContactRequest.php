<?php

namespace App\Http\Requests\Patient\Profile;

use App\Http\Requests\Patient\PatientRequest;
use App\Services\Patient\Profile\PatientProfileService;
use Illuminate\Validation\Rule;

/**
 * Adding or editing a next of kin or an emergency contact.
 *
 * The two are the same form and the same four fields; which list the entry
 * lands on is the `type` — carried in the route rather than the body, so it is
 * merged in here before validation to be checked like anything else.
 */
class ContactRequest extends PatientRequest
{
    /**
     * Fold the route segment into the payload so it is validated too.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['type' => $this->route('type')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'type' => ['required', 'string', Rule::in(PatientProfileService::CONTACT_TYPES)],
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'phone_number' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:30'],
            'relationship' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:100'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in(['Male', 'Female', 'Other', 'Unspecified'])],
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
            'type.in' => 'A contact is either a next_of_kin or an emergency_contact.',
            'name.required' => 'Enter the name of the contact.',
            'phone_number.required' => 'Enter a phone number for the contact.',
            'relationship.required' => 'Say how this person is related to you.',
        ];
    }

    /**
     * An edit here has to carry something to edit; see the base class.
     */
    protected function rejectEmptyUpdates(): bool
    {
        return true;
    }
}
