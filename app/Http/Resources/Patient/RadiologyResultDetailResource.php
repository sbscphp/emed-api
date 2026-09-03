<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A radiology report opened from the list.
 *
 * Serves both states of that screen. A released report carries its document
 * card and the radiologist's findings; one still being read carries neither,
 * and the app shows its "Report Not Available" panel instead.
 */
class RadiologyResultDetailResource extends JsonResource
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
        $results = collect($this->results ?: []);

        return [
            'id' => $this->id,
            'test_name' => $this->test_name,
            'department' => $this->department,
            'is_released' => $isReleased,
            'status' => $isReleased ? 'Released' : 'Pending',
            'hospital' => $hospital['name'] ?? null,
            'ordered_by' => $this->doctorName($this->orderedBy),
            'date' => optional($this->created_at)->format('d F Y'),
            'ordered_at' => optional($this->created_at)->toDateTimeString(),
            'released_at' => $isReleased ? optional($results->max('updated_at'))->toDateTimeString() : null,
            'released_on' => $isReleased ? optional($results->max('updated_at'))->format('d F Y') : null,

            'document' => $isReleased ? ($this->document ?: null) : null,

            // The radiologist's write up, so the app can show the report as well
            // as offer the PDF.
            'reports' => $isReleased
                ? $results->map(fn($result) => [
                    'id' => $result->id,
                    'examination_type' => $result->examination_type,
                    'clinical_indication' => $result->clinical_indication,
                    'technique' => $result->technique,
                    'findings' => $result->findings,
                    'image' => $result->result_img,
                ])->values()
                : [],

            'pending_message' => $isReleased
                ? null
                : 'Your radiology department is still processing this Report',
        ];
    }

    /**
     * Present the ordering doctor the way the app titles them.
     */
    protected function doctorName($doctor): ?string
    {
        if (!$doctor) {
            return null;
        }

        $name = $doctor->fullname ?: trim($doctor->first_name . ' ' . $doctor->last_name);

        if ($name === '') {
            return null;
        }

        return str_starts_with(strtolower($name), 'dr') ? $name : 'Dr. ' . $name;
    }
}
