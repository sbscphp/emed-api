<?php

namespace App\Http\Resources;

use App\Enums\AdmissionStatusEnums;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The Patient Information tab of an admitted patient, grouped the way the
 * screen reads it: the header strip, then personal information, location
 * information and the emergency contact.
 *
 * The raw patient attributes are kept alongside the grouped keys so the
 * consumers that read the flat model straight off this endpoint keep working.
 */
class AdmittedPatientProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Set by AdmissionService::getAdmittedPatientProfile — the stay this
        // profile describes. Null for a patient who has never been admitted.
        $admission = $this->currentAdmission;
        $ward = optional($admission)->ward;
        $lastVisit = $this->visits_recent;
        $arrival = $this->parseDate(optional($lastVisit)->arrival_date);

        return array_merge(parent::toArray($request), [
            // Kept from the previous payload shape.
            'visit_date' => $arrival,

            // The strip above the tabs: name, age, status, id, room and last visit.
            'header' => [
                'patient_name' => $this->fullName(),
                'age' => $this->resolveAge(),
                'age_label' => $this->resolveAge() === null ? null : $this->resolveAge() . ' Years Old',
                'status' => $this->headerStatus($admission),
                'hospital_id' => $this->patientno,
                'room_no' => $this->roomNo($admission, $ward),
                'last_visit' => $arrival ? $arrival->format('jS F, Y') . ' | ' . $arrival->format('g:i a') : null,
                'last_visit_date' => optional($arrival)->format('Y-m-d'),
                'last_visit_time' => optional($arrival)->format('g:i a'),
            ],

            'patient_information' => [
                'patient_name' => $this->fullName(),
                'date_of_birth' => optional($this->parseDate($this->dob))->format('d / m / Y'),
                'hospital_id' => $this->patientno,
                'gender' => $this->gender,
                'phone_number' => $this->phoneno,
                'age' => $this->resolveAge(),
            ],

            'location_information' => [
                'ward' => optional($ward)->name,
                // admitted_patients.bed carries the bed label, e.g. "Bed 7".
                'bed_space' => optional($admission)->bed,
                'bed_type' => $this->bedType($ward),
            ],

            // The screen shows a single contact, so the object is not a list.
            'emergency_contacts' => $this->contactCard(),
        ]);
    }

    /**
     * The patient's name as the header and the information card show it.
     *
     * @return string|null
     */
    protected function fullName()
    {
        return trim(implode(' ', array_filter([$this->firstname, $this->lastname]))) ?: null;
    }

    /**
     * The patient's age, computed from the date of birth when the column was
     * never filled in.
     *
     * @return int|null
     */
    protected function resolveAge()
    {
        if (!is_null($this->age) && $this->age !== '') {
            return (int) $this->age;
        }

        $dob = $this->parseDate($this->dob);

        return $dob ? $dob->age : null;
    }

    /**
     * The chip beside the patient's name. An admitted patient reads as Active;
     * every other stay shows the admission status it is actually in.
     *
     * @param  \App\Models\AdmittedPatient|null  $admission
     * @return string|null
     */
    protected function headerStatus($admission)
    {
        if (!$admission) {
            return $this->status;
        }

        return $admission->status === AdmissionStatusEnums::ADMITTED->value
            ? 'Active'
            : $admission->status;
    }

    /**
     * Where the patient is lying, built from the ward and the bed label.
     *
     * Wards hold no room number of their own, so the ward name and the bed are
     * all there is to show.
     *
     * @param  \App\Models\AdmittedPatient|null  $admission
     * @param  \App\Models\Ward|null  $ward
     * @return string|null
     */
    protected function roomNo($admission, $ward)
    {
        $parts = array_filter([optional($ward)->name, optional($admission)->bed]);

        return $parts ? implode('. ', $parts) : null;
    }

    /**
     * The bed type, which a ward carries as its own type — General, Private,
     * Adult or Children.
     *
     * @param  \App\Models\Ward|null  $ward
     * @return string|null
     */
    protected function bedType($ward)
    {
        $type = optional($ward)->type;

        if (empty($type)) {
            return null;
        }

        return str_ends_with(strtolower($type), 'bed') ? $type : $type . ' Bed';
    }

    /**
     * The emergency contact card.
     *
     * The screen labels the two name fields Surname and Last Name; they are the
     * contact's firstname and lastname columns in that order.
     *
     * @return array<string, mixed>|null
     */
    protected function contactCard()
    {
        $contact = $this->emergencyContact ?: $this->nextOfKin;

        if (!$contact) {
            return null;
        }

        return [
            'id' => $contact->id,
            'surname' => $contact->firstname,
            'last_name' => $contact->lastname,
            'gender' => $contact->gender,
            'phone_number' => $contact->phoneno,
            'state_of_origin' => $contact->stateoforigin,
            'lga' => $contact->lga,
            'home_address' => $contact->homeaddress,
            'relationship' => $contact->relationship,
        ];
    }

    /**
     * Parse a stored date without letting a malformed one break the payload.
     *
     * @param  mixed  $value
     * @return \Carbon\Carbon|null
     */
    protected function parseDate($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $th) {
            return null;
        }
    }
}
