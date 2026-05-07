<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Lab_Edit_Service_Request extends FormRequest
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
            "id" => "required|exists:tenant.lab_services,id",
            "name" => "required|string",
            "price" => "required|integer",
            "class" => "nullable|string",
            "type" => "required|string",
            "service_category_id" => "required|exists:tenant.service_categories,id",
        ];
    }
}
