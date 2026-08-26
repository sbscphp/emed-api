<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SuperAdminRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The role name is required.',
            'name.string'   => 'The role name must be a valid string.',
            'name.max'      => 'The role name may not be greater than 255 characters.',
            'description.string' => 'The description must be a valid string.',
            'description.max'    => 'The description may not be greater than 500 characters.',
        ];
    }
}
