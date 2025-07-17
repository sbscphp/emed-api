<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicineLogResouces extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            "Medicine Name" => optional($this->medication)->medicine_name ?? "",
            "Patient" => optional($this->patient)->cardno ?? "",
            "Prescribed_drug" => $this->presscribed_drug,
            "Patient_status" => $this->patient_status ?? "",
            "status" => $this->status,
            "action" => $this->action
        ];
    }
}
