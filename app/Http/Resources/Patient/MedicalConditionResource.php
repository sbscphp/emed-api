<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the patient app's "Medical conditions" screen.
 *
 * The screen reads "Diagnosed: Aug 2018", so the date is answered both as the
 * stored value and as that line.
 */
class MedicalConditionResource extends JsonResource
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
            'name' => $this->name,
            'diagnosed_at' => optional($this->diagnosed_at)->format('Y-m-d'),
            'diagnosed_label' => optional($this->diagnosed_at)->format('M Y'),
            'notes' => $this->notes,
            'source' => $this->source,
            'recorded_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
