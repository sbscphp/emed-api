<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A next of kin or an emergency contact.
 *
 * The tables keep a first and a last name because the admin registration form
 * asks for both; the app's form asks for one, so the two are joined back into
 * the single `name` it posted.
 */
class ContactResource extends JsonResource
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
            'name' => trim($this->firstname . ' ' . $this->lastname),
            'first_name' => $this->firstname,
            'last_name' => $this->lastname,
            'relationship' => $this->relationship,
            'phone_number' => $this->phoneno,
            'address' => $this->homeaddress,
            'gender' => $this->gender,
        ];
    }
}
