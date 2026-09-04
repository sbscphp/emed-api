<?php

namespace App\Http\Requests\Patient\Notification;

use App\Http\Requests\Patient\PatientRequest;
use App\Models\Notification;
use Illuminate\Validation\Rule;

/**
 * The patient app's notification list.
 *
 * Unpaginated by default, because the screen renders one scroll of rows grouped
 * under Today / Yesterday / Earlier rather than pages; `paginate` is there for
 * an account that has built up more history than one response should carry.
 */
class NotificationIndexRequest extends PatientRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::in(Notification::PATIENT_TYPES)],
            'is_read' => ['nullable', 'boolean'],
            'paginate' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'That is not a kind of notification this app sends.',
        ];
    }
}
