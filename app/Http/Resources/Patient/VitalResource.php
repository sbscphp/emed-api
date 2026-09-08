<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One sitting on the patient app's "Recorded Visits" list, and the same shape
 * the "Latest Readings" page renders.
 *
 * `readings` is attached by PatientVitalsService::decorate(), which is where the
 * six measurements are assembled and marked Normal, High or Low.
 */
class VitalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recorded_at' => optional($this->created_at)->toDateTimeString(),
            'recorded_on' => optional($this->created_at)->format('d M Y, h:i A'),
            'date' => optional($this->created_at)->format('Y-m-d'),
            'readings' => $this->readings ?: [],
        ];
    }
}
