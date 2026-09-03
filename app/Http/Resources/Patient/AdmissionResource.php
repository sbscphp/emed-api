<?php

namespace App\Http\Resources\Patient;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the patient app's "Previous Admissions" list: the date, the time,
 * the hospital and how the stay ended.
 */
class AdmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hospital = $this->hospital ?: [];

        return [
            'id' => $this->id,
            'admission_no' => $this->admission_no,
            'hospital' => $hospital['name'] ?? null,
            'date_admitted' => optional($this->date_admitted)->format('Y-m-d'),
            'date_label' => optional($this->date_admitted)->format('d F Y'),
            'time_admitted' => $this->formatTime($this->admission_time),
            'status' => $this->status,
            'is_active' => $this->status === 'Admitted',
            'ward' => optional($this->ward)->name,
            'bed' => $this->bedLabel(),
        ];
    }

    /**
     * The bed the patient was put in, however this tenant records it.
     *
     * Older rows carry a free text `bed` column; newer ones point at a bed row.
     */
    protected function bedLabel(): ?string
    {
        return optional($this->bedSpace)->bed_number ?: $this->bed;
    }

    /**
     * Present a stored time column in the 12 hour format the app reads.
     *
     * @param  string|null  $time
     * @return string|null
     */
    protected function formatTime($time)
    {
        if (empty($time)) {
            return null;
        }

        try {
            return Carbon::parse($time)->format('h:i A');
        } catch (\Throwable $th) {
            return $time;
        }
    }
}
