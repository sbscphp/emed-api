<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A doctor on the patient app's "Select your preferred doctor" screen.
 *
 * Deliberately thin. The landlord user row carries a staff member's email,
 * phone number and account state, and none of that belongs in a list a patient
 * can page through.
 */
class DoctorResource extends JsonResource
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
            'name' => $this->name(),
            'department' => $this->department_name,
            'profile_picture' => $this->profile_picture,
        ];
    }

    /**
     * Present the doctor the way the app titles them.
     */
    protected function name(): ?string
    {
        $name = $this->fullname ?: trim($this->first_name . ' ' . $this->last_name);

        if ($name === '') {
            return null;
        }

        return str_starts_with(strtolower($name), 'dr') ? $name : 'Dr. ' . $name;
    }
}
