<?php

namespace App\Http\Requests\Patient\Profile;

use App\Http\Requests\Patient\PatientRequest;
use Illuminate\Validation\Rule;

/**
 * The "Blood group and Genotype" form.
 *
 * Both are pickers on the screen rather than free text, so both are checked
 * against the list the picker offers — a blood group is not something to let a
 * typo into.
 */
class UpdateHealthInformationRequest extends PatientRequest
{
    /**
     * The blood groups the picker offers.
     *
     * @var array<int, string>
     */
    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /**
     * The genotypes the picker offers.
     *
     * @var array<int, string>
     */
    public const GENOTYPES = ['AA', 'AS', 'AC', 'SS', 'SC', 'CC'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'blood_group' => ['sometimes', 'nullable', 'string', Rule::in(self::BLOOD_GROUPS)],
            'genotype' => ['sometimes', 'nullable', 'string', Rule::in(self::GENOTYPES)],
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
            'blood_group.in' => 'Choose one of: ' . implode(', ', self::BLOOD_GROUPS) . '.',
            'genotype.in' => 'Choose one of: ' . implode(', ', self::GENOTYPES) . '.',
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
