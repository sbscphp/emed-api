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
            'name' => 'required|unique:tenant.roles,name,' . $this->route('id'),
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:tenant.permissions,name',
        ];
    }
}
