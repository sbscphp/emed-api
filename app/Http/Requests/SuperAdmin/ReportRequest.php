<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'search_param' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:1000',
            'page' => 'nullable|integer|min:1',
            'export' => 'nullable|boolean',
            'format' => 'nullable|in:excel,csv',
        ];
    }
}
