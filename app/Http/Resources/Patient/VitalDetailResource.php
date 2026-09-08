<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One sitting opened up: the same readings as the list row, under the header
 * saying where and by whom they were taken.
 *
 * `note` is always null for now — triage has no free text field, so the screen
 * shows its "No additional note" placeholder. It is answered rather than
 * omitted so the app does not have to treat a missing key as a special case
 * once the column exists.
 */
class VitalDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hospital = $this->hospital ?: [];
        $visit = $this->visit;

        return [
            'id' => $this->id,
            'hospital' => $hospital['name'] ?? null,
            'department' => optional(optional($visit)->service)->name,
            'recorded_at' => optional($this->created_at)->toDateTimeString(),
            'recorded_on' => optional($this->created_at)->format('d M, Y | h:i A'),
            'note' => null,
            // The screen's "Doctor" line. Vitals are taken during triage, so
            // this is whichever member of staff took them rather than the
            // consultant the patient goes on to see.
            'recorded_by' => $this->staffName($this->recordedBy),
            'visit_no' => optional($visit)->visitno,
            'readings' => $this->readings ?: [],
        ];
    }

    /**
     * Present the staff member who took the readings.
     */
    protected function staffName($user): ?string
    {
        if (!$user) {
            return null;
        }

        $name = $user->fullname ?: trim($user->first_name . ' ' . $user->last_name);

        if ($name === '') {
            return null;
        }

        return str_starts_with(strtolower($name), 'dr') ? $name : 'Dr. ' . $name;
    }
}
