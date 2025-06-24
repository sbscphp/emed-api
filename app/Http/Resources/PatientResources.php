<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResources extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            "patient_id"=>$this->patient_id,
            "arrival_date"=>$this->arrival_date,
            "visitno"=>$this->visitno,
            "stage"=>$this->stage,
            "status"=> $this->status,
            "updated_at"=>$this->updated_at,
            "created_at"=>$this->created_at,
            "patient_age"=>optional($this->patient)?->age,
            "gender"=>optional($this->patient)?->gender
        ];
    }
}
