<?php

namespace App\Http\Resources;

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
//     $sellingPrice = optional($this->medication)->selling_price ?? 0;
//      $totalPrice = $this->received_qty * $sellingPrice;
//   return [
//                   'id' => $this->id,
//                    'BatchNo' => $this->batch_no,
//                   'ShipmentStatus' => $this->shipment_status,
//                   'MedicineName' => $this->medication->medicine_name ?? '',
//                   'BrandName' => $this->medication->brand_name ?? '',
//                   'GenericName' => $this->medication->generic_name ?? '',
//                   'Pharmacy' => $this->pharmacy->name ?? '',
//                   'ReceivedQty' => $this->received_qty,
//                  'SellingPrice' => $sellingPrice,
//                    'TotalPrice' => $totalPrice,
//                   'CreatedAt' => $this->created_at->toDateTimeString(),
//         ];


 $medication = optional($this->medication);
    $pharmacy = optional($this->pharmacy);
    $sellingPrice = $medication->selling_price ?? 0;
    $totalPrice = $this->received_qty * $sellingPrice;

    return [
        'id' => $this->id,
        'BatchNo' => $this->batch_no,
        'ShipmentStatus' => $this->shipment_status,
        'MedicineName' => $medication->medicine_name ?? '',
        'BrandName' => $medication->brand_name ?? '',
        'GenericName' => $medication->generic_name ?? '',
        'Pharmacy' => $pharmacy->name ?? '',
        'ReceivedQty' => $this->received_qty,
        'SellingPrice' => (float) $sellingPrice,
        'TotalPrice' => (float) $totalPrice,
        'CreatedAt' => optional($this->created_at)->toDateTimeString(),
    ];
    }
}
