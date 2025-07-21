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
        return [
            'Patient Name' => $this->patient?->firstname . " " . $this->patient?->lastname,
            'Consulted By' => $this->pharmacist?->first_name . " " . $this->pharmacist?->last_name,
            "Patient No" => $this->patientno ?? "",
            "Date" => $this->created_at ? Carbon::parse($this->created_at)->format('Y-m-d') : "",
            "Test Status" => $this->test_name ?? "",
            "Payment Status" => $this->patient?->visits_recent?->billingLogsForPatient?->payment_status ?? ""
        ];
    }
}
