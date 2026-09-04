<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A hospital opened from the Linked Hospitals list.
 *
 * The contact details a patient would use to reach the hospital, plus the
 * number that hospital files them under. Nothing about the hospital's licence,
 * database or subscription: this is the card a patient reads, not an admin view.
 */
class HospitalDetailResource extends JsonResource
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

            'relationship' => $this->relationship,
            'is_primary' => (bool) $this->is_primary,

            'address' => $this->address,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'state_city' => $this->state_city,
            'country' => $this->country,
            'hospital_type' => $this->hospital_type,

            // What this hospital knows the patient as. Null when they have been
            // linked but no record has been opened for them yet.
            'patient_number' => $this->patient_number,
            'linked_at' => $this->linked_at,
        ];
    }
}
