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
            "name" => $this->serviceUnit->name ?? "",
            "total_invoice" => strval(abs(intval($this->total_invoice))),
            "amout_paid" => $this->amount_paid ?? 0,
            "outstanding_amount" => strval(abs(intval($this->outstanding_amount))),
            //"service_unit_id" => $this->service_unit_id,
            "total_billing" => strval(abs(intval($this->total_billing))),




        ];
    }
}
