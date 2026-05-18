<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WardBedRequest extends FormRequest
{
    private const WARD_TYPES = ['Children', 'Adult', 'General', 'Private'];
    private const WARD_GENDERS = ['Male', 'Female', 'Unisex'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', self::WARD_TYPES),
            'gender' => 'required|string|in:' . implode(',', self::WARD_GENDERS),
            'cost' => 'nullable|numeric|min:0',
            'bed_number' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ward name is required.',
            'type.required' => 'Ward type is required.',
            'type.in' => 'Ward type must be one of: ' . implode(', ', self::WARD_TYPES) . '.',
            'gender.required' => 'Ward gender is required.',
            'gender.in' => 'Ward gender must be one of: ' . implode(', ', self::WARD_GENDERS) . '.',
            'cost.min' => 'Ward cost must be a positive number.',
            'cost.numeric' => 'Ward cost must be a valid number.',
            'bed_number.min' => 'Bed number must be a positive integer.',
        ];
    }
}
