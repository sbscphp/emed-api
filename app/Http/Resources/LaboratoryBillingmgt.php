<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LaboratoryBillingmgt extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "name" => $this->lab_dept,
            "class" => $this->ordered_test,
            "price" => $this->patient?->visits_recent?->billingLogsForPatient?->grand_total
        ];
    }
}
