<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the admissions table.
 *
 * The raw model attributes are kept so existing consumers keep working; the
 * keys below are the ones the admissions listing (All, Active, Scheduled,
 * Discharged and Cancelled tabs) renders.
 */
class AdmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $patient = $this->patient;
        $doctor = $this->doctor;
        $ward = $this->ward;

        return array_merge(parent::toArray($request), [
            'admission_no' => $this->admission_no,

            // Patient Name / Card No
            'patient' => [
                'id' => optional($patient)->id,
                'name' => trim(optional($patient)->firstname . ' ' . optional($patient)->lastname) ?: null,
                'card_no' => optional($patient)->cardno,
                'patient_no' => optional($patient)->patientno,
                'gender' => optional($patient)->gender,
                'age' => optional($patient)->age,
            ],

            'admission_type' => $this->admission_type,

            // Department Type
            'department' => [
                'id' => optional($this->department)->id,
                'name' => optional($this->department)->name,
            ],

            // Doctor / Consultant
            'doctor' => [
                'id' => optional($doctor)->id,
                'name' => optional($doctor)->fullname
                    ?: (trim(optional($doctor)->first_name . ' ' . optional($doctor)->last_name) ?: null),
            ],

            // Admission Date (date + time, as the table stacks them)
            'admission_date' => optional($this->date_admitted)->format('Y-m-d'),
            'admission_time' => $this->formatTime($this->admission_time),

            // Expected Admission (Scheduled tab) — also the Cancelled tab's scheduled date
            'expected_admission_date' => optional($this->expected_admission_date)->format('Y-m-d'),
            'expected_admission_time' => $this->formatTime($this->expected_admission_time),
            'scheduled_date' => optional($this->expected_admission_date)->format('Y-m-d'),

            // Ward / Bed — the preferred ward and bed while still scheduled
            'ward' => [
                'id' => optional($ward)->id,
                'name' => optional($ward)->name,
                'type' => optional($ward)->type,
            ],
            'bed' => $this->bed,
            'ward_bed' => $this->ward_bed,

            // Discharged tab
            'discharged_date' => optional($this->date_discharged)->format('Y-m-d'),
            'discharge_time' => $this->formatTime($this->discharge_time),
            'length_of_stay' => $this->length_of_stay,

            // Cancelled tab
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_date' => optional($this->cancelled_at)->format('Y-m-d H:i'),

            'payment_status' => $this->payment_status,
            'status' => $this->status,
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ]);
    }

    /**
     * Present a stored time column the way the admissions table shows it.
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
