<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationBillingmgt extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "name" => $this->name,
            "patient" => $this->patients->patientno ?? "",
            "price" => $this->patients->visits_recent->billingLogsForPatient->grand_total ?? ""
        ];
    }
}
