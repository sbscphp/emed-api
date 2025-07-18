<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amount  = $this->visits_recent?->billingLogsForPatient?->grand_total;
        return [
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'gender' => $this->gender,
            'age' => Carbon::parse($this->dob)->age,
            'patientno' => $this->patientno,
            'created_at' => Carbon::parse($this->created_at)->format('Y-m-d'),
            'amount' => $amount ?? ""
        ];
    }
}
