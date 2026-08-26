<?php

namespace App\Http\Resources;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationInventoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {



        $medication = optional($this->medication);
        $pharmacy = optional($this->pharmacy);
        $sellingPrice = $medication->selling_price ?? 0;
        $totalPrice = $this->received_qty * $sellingPrice;
        $vendor  = $this->vendor_id ? Vendor::find(intval($this->vendor_id)) : null;
        return [
            'id' => $this->id,
            'batch_no' => $this->batch_no,
            'shipment_no' => $this->shipment_no,
            'shipment_status' => $this->shipment_status,
            'medicine_name' => $medication->medicine_name,
            'vendor' => $vendor->vendor_name ?? "",
            'manufacturer' => $medication->manufacturer,
            'brand_name' => $this->brand_name,
            'generic_name' => $medication->generic_name,
            'pharmacy' => $pharmacy->name,
            'medicine_type' => $medication->medicine_type,
            'received_qty' => $this->received_qty,
            'price' => (float)$this->price,
            'selling_price' => (float) $sellingPrice,
            'total_price' => (float) $totalPrice,
            'createdAt' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
