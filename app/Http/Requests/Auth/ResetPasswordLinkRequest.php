<?php

namespace App\Http\Requests\Auth;

use App\Rules\Auth\ValidateIdentifier;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordLinkRequest extends FormRequest
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
            'email' => ['required', 'email'],
        ];
    }


    public function messages(): array
    {
        return [
            'email.required' => 'Email  is required',
            'email.email' => 'Invalid email',
        ];
    }
}
