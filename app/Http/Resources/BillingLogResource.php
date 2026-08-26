<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $patient = $this->patient;
        return [
            "patient name" => $patient->firstname . " " . $patient->lastname,
            "Invoice Number" => $this->invoice_number ?? " ",
            "Total Number" => $this->grand_total ?? " ",
            "Payment Method" => $this->payment_method ?? " ",
            "Service Type" => $this->serviceType?->name ?? " ",
            "Date" => Carbon::parse($this->created_at)->format('M d Y g:ia'),
            "Payment Status" => $this->payment_status ?? ""
        ];
    }
}
