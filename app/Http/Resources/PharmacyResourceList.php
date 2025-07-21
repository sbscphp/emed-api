<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PharmacyResourceList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $patient = optional($this->treatments_one?->patient);
        return [
            "Patient Name" => $patient->firstname . " " . $patient->lastname,
            "Registration Number" => $patient->patientno ?? "",
            "Consulted by" => $this->pharmacist?->first_name . " " . $this->pharmacist?->last_name,
            "age" => Carbon::parse($patient->dob)->age,
            "amount" => $this->payment_status ?? "",
            "Date Billed" => $patient?->visits_recent?->billingLogsForPatient?->created_at ? Carbon::parse($patient?->visits_recent?->billingLogsForPatient?->created_at)->format('Y-m-d') : "",
        ];
    }
}
