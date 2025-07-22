<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogRequest extends FormRequest
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
            'search' => 'nullable|alpha_num',
            'sort_by' => 'nullable|in:recent,oldest',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|after_or_equal:start_date',
            'activity_type' => 'nullable|string'
        ];
    }
}
