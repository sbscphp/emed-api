<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Billingresource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service_type = optional($this->service_type);
        $service_unit  = optional($this->service_unit);
        return [
            "invoice_number" => $this->invoice_number,
            "patient_id" => $this->patient_id,
            "visit_id" => $this->visit_id,
            "patient_name" => $this->patient_name,
            "billing_date" => $this->billing_date,
            "service_type_id" => $this->service_type_id,
            "service_unit_id" => $this->service_unit_id,
            "item_name" => $this->item_name,
            "unit_price" => $this->unit_price,
            "quantity" => $this->quantity,
            "payment_status" => $this->payment_status,
            "deposit_amount" => $this->deposit_amount,
            "payment_method" => $this->payment_method,
            "sub_total" => $this->sub_total,
            "tax_amount" => $this->tax_amount,
            "grand_total" => $this->grand_total,
            "service_name" => $this->service_type?->name ?? "",
            "service_unit" => $this->service_unit?->name ?? ""
        ];
    }
}
