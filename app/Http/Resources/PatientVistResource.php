<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientVistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    // PatientVisit::with(['patient', 'patient.triage'])->get();
    public function toArray(Request $request): array
    {
        return [
            "patient name" => $this->patient?->firstname . " " . $this->patient?->lastname,
            "patient type" => $this->patient?->patient_type,
            "patient Number" => $this->patient?->patientno,
            "arrival_date" => $this->arrival_date,
            "departure_date" => $this->departure_date,
            // "Acuity" => $this->patient?->reg_status,
            "patient status" => $this->patient?->status
        ];
    }
}
