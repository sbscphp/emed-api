<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the patient app's "Linked Hospitals" list.
 *
 * `uuid` is what the app sends back as X-Tenant-ID to read anything at this
 * hospital, so it leads the payload; `hospital_id` is the short code the screen
 * prints, which is for the patient to read and quote, not for the app to key on.
 */
class HospitalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'hospital_id' => $this->hospital_code,
            'logo' => $this->logo,

            // "Primary Hospital" or "Linked". Answered twice, once as the words
            // the screen prints and once as the boolean it should switch on.
            'relationship' => $this->relationship,
            'is_primary' => (bool) $this->is_primary,
        ];
    }
}
