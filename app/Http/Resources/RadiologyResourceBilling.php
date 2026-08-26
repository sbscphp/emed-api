<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class RadiologyResourceBilling extends JsonResource
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
            "patientno" => $patient?->patientno,
            "gender" => $patient?->gender,
            "age" => Carbon::parse($patient?->dob)->age,
            "amount" => $this->grand_total,
            'created_at' => Carbon::parse($this->created_at)->format('Y-m-d'),

        ];
    }
}
