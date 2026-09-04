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

            'created_at' => optional($this->created_at)->toDateTimeString(),
            'date' => optional($this->created_at)->format('d M Y'),
            'time' => optional($this->created_at)->format('h:i A'),
            'time_ago' => optional($this->created_at)->diffForHumans(),

            // What the row opens. Kept as the service wrote it, so a new kind of
            // notification needs no change here.
            'data' => $data,

            // The one action the screen offers, named so the app does not have
            // to map every type to a button label of its own.
            'action' => $this->action(),
        ];
    }

    /**
     * The button at the bottom of the detail screen, when there is one.
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
