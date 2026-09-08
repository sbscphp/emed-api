<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One service in the services and pricing catalogue, together with what its
 * sub-services say about its price.
 */
class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $count = $this->sub_services_count;
        $min = $this->sub_services_min_price;
        $max = $this->sub_services_max_price;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'status' => (bool) $this->status,
            'status_label' => $this->status ? 'Active' : 'Inactive',
            'sub_services_count' => (int) $count,
            'min_price' => $min !== null ? (float) $min : null,
            'max_price' => $max !== null ? (float) $max : null,
            // What the table shows in its Price column: an exact figure when
            // every sub-service costs the same, "From x" when they differ.
            'price_label' => $this->priceLabel($min, $max),
            'sub_services' => $this->whenLoaded(
                'subServices',
                fn() => BillingServiceResource::collection($this->subServices)
            ),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * Present the price range of the sub-services as the table reads it.
     *
     * @param  mixed  $min
     * @param  mixed  $max
     * @return string
     */
    protected function priceLabel($min, $max): string
    {
        if ($min === null) {
            return number_format((float) $this->price, 2);
        }

        $min = (float) $min;
        $max = (float) ($max ?? $min);

        return $min === $max
            ? number_format($min, 2)
            : 'From ' . number_format($min, 2);
    }
}
