<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResourceRecent extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'dob' => Carbon::parse($this->dob)->format('d-m-Y'),
            'gender' => $this->gender,
            'arrival_date' => $this->patient_visits_latest?->arrival_date ? Carbon::parse($this->patient_visits_latest?->arrival_date)->format('d-m-Y') : null,
            'departure_date' => $this->patient_visits_latest?->arrival_date ? Carbon::parse($this->patient_visits_latest?->arrival_date)->format('d-m-Y') : null,
            'departure_date' => $this->patient_visits_latest?->arrival_date ? Carbon::parse($this->patient_visits_latest?->arrival_date)->format('d-m-Y') : null,
            'status' => $this->patient_visits_latest?->status
        ];
    }
}
