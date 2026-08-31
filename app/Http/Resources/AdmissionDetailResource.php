<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single admission, grouped the way the detail drawer reads it: patient
 * information, schedule information, admission information, stay information,
 * financial information and — when it applies — cancellation information.
 */
class AdmissionDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $patient = $this->patient;
        $ward = $this->ward;

        return array_merge(parent::toArray($request), [
            'admission_no' => $this->admission_no,
            'status' => $this->status,

            'patient_information' => [
                'id' => optional($patient)->id,
                'patient_name' => trim(optional($patient)->firstname . ' ' . optional($patient)->lastname) ?: null,
                'hospital_id' => optional($patient)->patientno,
                'card_no' => optional($patient)->cardno,
                'date_of_birth' => optional($patient)->dob,
                'gender' => optional($patient)->gender,
                'age' => optional($patient)->age,
                'phone_number' => optional($patient)->phoneno,
                'address' => optional($patient)->homeaddress,
            ],

            'schedule_information' => [
                'expected_admission_date' => optional($this->expected_admission_date)->format('Y-m-d'),
                'expected_time' => $this->formatTime($this->expected_admission_time),
                'preferred_ward' => optional($ward)->name,
                'preferred_bed_space' => $this->bed,
            ],

            'admission_information' => [
                'reason' => $this->reason,
                'admission_type' => $this->admission_type,
                'department' => optional($this->department)->name,
                'doctor' => $this->userName($this->doctor),
                'referred_by' => $this->referred_by,
                'ward' => optional($ward)->name,
                'bed' => $this->bed,
                'notes' => $this->notes,
                'admission_date' => optional($this->date_admitted)->format('Y-m-d'),
                'admission_time' => $this->formatTime($this->admission_time),
                'admitted_by' => $this->userName($this->admittedBy),
            ],

            'stay_information' => [
                'admission_date' => optional($this->date_admitted)->format('Y-m-d'),
                'discharge_date' => optional($this->date_discharged)->format('Y-m-d'),
                'discharge_time' => $this->formatTime($this->discharge_time),
                'length_of_stay' => $this->length_of_stay,
                'discharged_by' => $this->userName($this->dischargedBy),
                'discharge_notes' => $this->discharge_notes,
            ],

            'financial_information' => [
                'payer_type' => $this->payer_type,
                'deposit_amount_paid' => $this->deposit_amount,
                'payment_status' => $this->payment_status,
                'invoice_number' => optional($this->billing)->invoice_number,
                'total_amount' => optional($this->billing)->total_amount,
                'amount_outstanding' => optional($this->billing)->amount_outstanding,
            ],

            'cancellation_information' => $this->when($this->cancelled_at || $this->cancellation_reason, [
                'cancelled_date' => optional($this->cancelled_at)->format('Y-m-d, h:i A'),
                'cancelled_by' => $this->userName($this->cancelledBy),
                'cancellation_reason' => $this->cancellation_reason,
            ]),

            'visit' => [
                'id' => optional($this->visit)->id,
                'visit_no' => optional($this->visit)->visitno,
            ],
        ]);
    }

    /**
     * Present a related user by their most complete name.
     *
     * @param  \App\Models\User|null  $user
     * @return string|null
     */
    protected function userName($user)
    {
        if (!$user) {
            return null;
        }

        return $user->fullname ?: (trim($user->first_name . ' ' . $user->last_name) ?: $user->email);
    }

    /**
     * Present a stored time column in the 12 hour format the drawer uses.
     *
     * @param  string|null  $time
     * @return string|null
     */
    protected function formatTime($time)
    {
        if (empty($time)) {
            return null;
        }

        try {
            return Carbon::parse($time)->format('h:i A');
        } catch (\Throwable $th) {
            return $time;
        }
    }
}
