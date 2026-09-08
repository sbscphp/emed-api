<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the patient app's notification list, and the detail screen behind
 * it.
 *
 * `type` is what the app switches on: it picks the icon, and with `data` it says
 * what tapping the row should open. The deep link itself is not composed here —
 * the app owns its own routing, so the payload names a kind of record and an id
 * and leaves the screen to the app.
 *
 * `hospital` rides on every row, and `billing` on every row that names a bill.
 * Both are put on the model by PatientNotificationService, which is where the
 * lookups belong.
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?: [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,

            'is_read' => !$this->is_unread,
            'read_at' => optional($this->read_at)->toDateTimeString(),

            // Which hospital the row came from. On every notification, because
            // an account is shared by the hospitals that registered it and the
            // message alone does not say which one is talking.
            'hospital' => $this->hospital ?: null,

            'created_at' => optional($this->created_at)->toDateTimeString(),
            'date' => optional($this->created_at)->format('d M Y'),
            'time' => optional($this->created_at)->format('h:i A'),
            'time_ago' => optional($this->created_at)->diffForHumans(),

            // What the row opens. Kept as the service wrote it, so a new kind of
            // notification needs no change here.
            'data' => $data,

            // The invoice behind a billing notification: its number, what it
            // stands paid at, and how it was paid. On the list as well as the
            // detail screen; null only on a row that names no bill.
            'billing' => $this->billing ?: null,

            // The one action the screen offers, named so the app does not have
            // to map every type to a button label of its own.
            'action' => $this->action(),
        ];
    }

    /**
     * The button at the bottom of the detail screen, when there is one.
     *
     * Matched on the notification's type, which is one of
     * Notification::PATIENT_TYPES. An invoice number or a payment method is a
     * field on the bill rather than a kind of notification, so neither belongs
     * here — they are answered in the `billing` block above.
     *
     * @return array<string, string>|null
     */
    protected function action(): ?array
    {
        return match ($this->type) {
            'lab_result_ready' => ['label' => 'View Result', 'target' => 'laboratory'],
            'radiology_result_ready' => ['label' => 'View Report', 'target' => 'radiology'],
            'payment_successful' => ['label' => 'View Invoice', 'target' => 'billing'],
            'payment_failed' => ['label' => 'Try Again', 'target' => 'billing'],
            'bill_created' => ['label' => 'View Bill', 'target' => 'billing'],
            'payment_support_received',
            'payment_support_completed' => ['label' => 'View Support', 'target' => 'payment_support'],
            'appointment_reminder',
            'appointment_update' => ['label' => 'View Appointment', 'target' => 'appointment'],
            default => null,
        };
    }
}
