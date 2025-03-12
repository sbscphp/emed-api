<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationRequest extends FormRequest
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
            'visitno' => 'required|string',
            'complaint' => 'required|string',
            'complaint_history' => 'required|string',
            'review' => 'required|string',
            'diagnosis' => 'required|string',
            'allergy' => 'nullable|string',
            'disease_pattern' => 'nullable|string',
            'disease_type' => 'nullable|string',
            'investigation' => 'nullable|in:laboratory,radiology,both',
            'follow_up' => 'nullable|boolean',
            'followUp_date' => 'nullable|date',
            'referral' => 'nullable|boolean',
            'referral_detail' => 'nullable|date',
            'admitted' => 'nullable|boolean'
        ];

    }
}
