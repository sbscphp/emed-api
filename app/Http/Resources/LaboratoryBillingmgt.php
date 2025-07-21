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
            // patient.laboratory
            "name" => $this->serviceUnit?->name ?? "",
            "class" => $this->patient?->laboratory?->ordered_test ?? "",
            "price" => $this->grand_total ?? ""
        ];
    }
}
