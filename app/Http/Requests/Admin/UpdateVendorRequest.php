<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_name'      => 'sometimes|required|string|max:255',
            'contact_person'   => 'nullable|string|max:255',
            'phone_number'     => 'sometimes|required|string|max:20',
            'email'            => 'nullable|email|max:255',
            'address'          => 'nullable|string|max:500',
            'registration_no'  => 'nullable|string|max:100',
            'status'           => 'sometimes|required|in:Active,Inactive',
            'category'          => 'required'
        ];
    }
}
