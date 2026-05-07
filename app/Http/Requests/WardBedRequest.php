<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WardBedRequest extends FormRequest
{
    private const WARD_TYPES = ['Children', 'Adult', 'General'];
    private const WARD_GENDERS = ['Male', 'Female', 'Unisex'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $wardRules = $this->isMethod('post')
            ? [
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:' . implode(',', self::WARD_TYPES),
                'gender' => 'required|string|in:' . implode(',', self::WARD_GENDERS),
            ]
            : [
                'name' => 'sometimes|required|string|max:255',
                'type' => 'sometimes|required|string|in:' . implode(',', self::WARD_TYPES),
                'gender' => 'sometimes|required|string|in:' . implode(',', self::WARD_GENDERS),
            ];

        return [
            ...$wardRules,
            'bed_cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
            'bed_count' => 'nullable|integer|min:1',
            'number_of_beds' => 'nullable|integer|min:1',
            'total_beds' => 'nullable|integer|min:1',
            'bed_space' => 'nullable|integer|min:1',
            'bed_spaces' => 'nullable|integer|min:1',
            'beds' => 'nullable|array',
            'beds.*.bed_number' => 'nullable|string|max:50',
            'beds.*.number_of_available' => 'nullable|integer|min:0',
            'beds.*.occupied' => 'nullable|boolean',
            'beds.*.status' => 'nullable|boolean',
        ];
    }
}
