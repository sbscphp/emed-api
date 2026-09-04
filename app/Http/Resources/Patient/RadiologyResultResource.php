<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the patient app's "Radiology" list.
 *
 * Same shape as its laboratory counterpart, with the wording that screen uses:
 * a released radiology report reads "Released" where a laboratory one reads
 * "Available".
 */
class RadiologyResultResource extends JsonResource
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

            // The test this was ordered from, which /{testId}/history is asked
            // for by. Null on rows written before it was recorded.
            'test_id' => $this->test_id,

            'test_name' => $this->test_name,
            'department' => $this->department,
            'hospital' => $hospital['name'] ?? null,
            'date' => optional($this->created_at)->format('Y-m-d'),
            'date_label' => optional($this->created_at)->format('d M Y'),
            'is_released' => $isReleased,
            'status' => $isReleased ? 'Released' : 'Pending',
            'has_report' => $isReleased,
            'report_note' => $isReleased ? 'PDF Report Available' : null,
        ];
    }
}
