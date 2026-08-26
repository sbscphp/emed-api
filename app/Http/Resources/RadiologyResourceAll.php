<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RadiologyResourceAll extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // consultation.patient
        // consultation.patientVisit.billingLogsForPatient
        return [
            'Patient Name' => $this->consultation?->patient?->firstname . " " . $this->consultation?->patient?->lastname,
            'Consulted By' => $this->consulted_by?->first_name . " " . $this->consulted_by?->last_name,
            "Patient No" => $this->consultation?->patient?->patientno ?? "",
            "Date" => $this->created_at ? Carbon::parse($this->created_at)->format('Y-m-d') : "",
            "Test Status" => $this->test_name ?? "",
            "Payment Status" => $this->consultation?->patientVisit?->billingLogsForPatient?->payment_status ?? ""
        ];
    }
}
