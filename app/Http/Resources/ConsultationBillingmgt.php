<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationBillingmgt extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "name" => $this->patient?->service?->name ?? "",
            "price" => $this->patient?->billingLogsForPatient?->grand_total ?? ""
        ];
    }
}
