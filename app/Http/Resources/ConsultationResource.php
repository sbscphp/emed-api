<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $patient =  optional($this->patient);
        return [
            "firstname" => $patient?->firstname,
            "lastname" => $patient?->lastname,
            "gender" => $patient?->gender,
            "age" => Carbon::parse($patient?->dob)->age,
            "amount" => $patient?->visits_recent?->billingLogsForPatient?->payment_status,
            'created_at' => Carbon::parse($patient?->visits_recent?->billingLogsForPatient?->created_at)->format('Y-m-d'),
        ];
    }
}
