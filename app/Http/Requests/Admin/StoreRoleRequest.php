<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|unique:tenant.roles,name',
            'display_name' => 'nullable',
            'description' =>  'nullable',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:tenant.permissions,name',
        ];
    }
}
