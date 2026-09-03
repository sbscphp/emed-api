<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the patient app's "Laboratory Results" list.
 *
 * `hospital` is attached by the service; the status is answered twice, once as
 * the word the screen prints and once as a boolean, so the app switches on the
 * boolean and never on the wording.
 */
class LabResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hospital = $this->hospital ?: [];
        $isReleased = (bool) $this->is_released;

        return [
            'id' => $this->id,
            'test_name' => $this->test_name,
            'department' => $this->department,
            'hospital' => $hospital['name'] ?? null,
            'date' => optional($this->created_at)->format('Y-m-d'),
            'date_label' => optional($this->created_at)->format('d M Y'),
            'is_released' => $isReleased,
            'status' => $isReleased ? 'Available' : 'Pending',
            'has_report' => $isReleased,
            'report_note' => $isReleased ? 'PDF Report Available' : null,
        ];
    }
}
