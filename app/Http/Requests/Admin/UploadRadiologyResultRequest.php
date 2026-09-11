<?php

namespace App\Http\Requests\Admin;

use App\Rules\ResultFile;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Releasing a radiology result as a scanned report.
 *
 * The document may be sent as `file` or as `file_url`, and either name accepts
 * any of the three shapes the API takes - a multipart upload, a base64 payload
 * or an already hosted URL. At least one of the two must be present; which one
 * the client uses is left to the client.
 */
class UploadRadiologyResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:tenant.patient_visit_radiology,id',
            // The presence check sits on `file` alone: it already fails when
            // both are missing, and putting it on both only reports the one
            // missing document twice.
            'file' => ['required_without:file_url', 'nullable', new ResultFile()],
            'file_url' => ['nullable', new ResultFile()],
            'file_name' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'The radiology test id is required.',
            'id.exists' => 'Radiology test not found.',
            'file.required_without' => 'A result file is required.',
        ];
    }
}
