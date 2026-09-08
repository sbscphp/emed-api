<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One priced sub-service of a service, as the sub-services table reads it.
 */
class BillingServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'price' => $this->price,
            'status' => (bool) $this->status,
            'status_label' => $this->status ? 'Active' : 'Inactive',
            'service' => [
                'id' => $this->service_id,
                'name' => optional($this->service)->name,
            ],
            'department' => [
                'id' => $this->department_id,
                'name' => optional($this->department)->name,
            ],
            'service_unit' => [
                'id' => optional($this->serviceUnit)->id,
                'name' => optional($this->serviceUnit)->name,
            ],
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
