<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the patient app's "Allergies" screen.
 *
 * `type_label` is what the row prints under the name — "Drug Allergy" rather
 * than "Drug" — so the app is not left composing clinical wording itself.
 */
class AllergyResource extends JsonResource
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
            'type' => $this->type,
            'type_label' => $this->type ? $this->type . ' Allergy' : null,
            'reaction' => $this->reaction,
            'source' => $this->source,
            'recorded_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
