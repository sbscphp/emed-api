<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "Name" => $this->medication?->medicine_name ?? "",
            "Active Ingredient" => $this->medication?->medicine_type ?? "",
            "selling_price" => $this->medication->selling_price ?? "",
            "Registration Number" => $this->pharmacy_id ?? "",
        ];
    }
}
