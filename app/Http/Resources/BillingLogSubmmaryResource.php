<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingLogSubmmaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "service_unit_id" => $this->service_unit_id,
            "total_billing" => $this->total_billing,
            "amout_paid" => $this->amout_paid ?? 0,
            "outstanding_amount" => $this->outstanding_amount,
            "total_invoice" => $this->total_invoice,
            "name" => $this->serviceUnit->name ?? "",

        ];
    }
}
