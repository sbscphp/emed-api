<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'nullable',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:tenant.permissions,id',
        ];
    }
}
