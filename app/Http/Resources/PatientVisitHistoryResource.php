<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A patient visit history row.
 *
 * The existing visit payload is returned untouched; this only adds the ward,
 * bed and attending doctor the admission history table needs.
 */
class PatientVisitHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $admission = $this->admission;
        $doctor = optional($admission)->doctor ?: optional($this->consultation)->consultedDoctor;

        return array_merge(parent::toArray($request), [
            'admission_date' => optional(optional($admission)->date_admitted)->format('Y-m-d'),
            'discharge_date' => optional(optional($admission)->date_discharged)->format('Y-m-d'),
            'ward' => [
                'id' => optional(optional($admission)->ward)->id,
                'name' => optional(optional($admission)->ward)->name,
            ],
            'bed' => optional($admission)->bed,
            'attending_doctor' => [
                'id' => optional($doctor)->id,
                'name' => $this->doctorName($doctor),
            ],
        ]);
    }

    /**
     * Present the attending doctor by their most complete name.
     *
     * @param  \App\Models\User|null  $doctor
     * @return string|null
     */
    protected function doctorName($doctor)
    {
        if (!$doctor) {
            return null;
        }

        return $doctor->fullname ?: (trim($doctor->first_name . ' ' . $doctor->last_name) ?: $doctor->email);
    }
}
