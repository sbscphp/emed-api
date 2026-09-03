<?php

namespace App\Http\Resources\Patient;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One admission opened from the list.
 *
 * Serves both states of that screen. An active admission has no discharge date,
 * no duration and no discharge summary, and the app paints its green "Active
 * admission" header instead of the blue "Discharged" one — `is_active` is what
 * it switches on.
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
        $hospital = $this->hospital ?: [];
        $isActive = $this->status === 'Admitted';

        return [
            'id' => $this->id,
            'admission_no' => $this->admission_no,
            'status' => $this->status,
            'is_active' => $isActive,
            'hospital' => $hospital['name'] ?? null,
            'ward' => optional($this->ward)->name,
            'bed' => optional($this->bedSpace)->bed_number ?: $this->bed,
            'department' => optional($this->department)->name,
            'admission_type' => $this->admission_type,

            'date_admitted' => optional($this->date_admitted)->format('Y-m-d'),
            'admitted_on' => $this->moment($this->date_admitted, $this->admission_time),
            'date_discharged' => optional($this->date_discharged)->format('Y-m-d'),
            'discharged_on' => $isActive ? null : $this->moment($this->date_discharged, $this->discharge_time),

            // Days on the ward. An active stay is still running, so it counts up
            // to today rather than to a discharge that has not happened.
            'duration' => $this->durationLabel(),

            'admitting_doctor' => $this->doctorName($this->doctor),
            'reason' => $this->reason,
            'discharge_summary' => $isActive ? null : $this->discharge_notes,
            'referred_by' => $this->referred_by,
        ];
    }

    /**
     * "6 Days", or nothing at all when there is no admission date to count from.
     */
    protected function durationLabel(): ?string
    {
        $days = $this->length_of_stay;

        if ($days === null) {
            return null;
        }

        return $days . ' ' . ($days === 1 ? 'Day' : 'Days');
    }

    /**
     * Join a date column and its time column into the one line the screen reads.
     *
     * @param  mixed  $date
     * @param  string|null  $time
     * @return string|null
     */
    protected function moment($date, $time): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            $moment = Carbon::parse($date->format('Y-m-d') . ' ' . ($time ?: '00:00:00'));

            return $time
                ? $moment->format('jS F Y . h:i A')
                : $moment->format('jS F Y');
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Present the admitting doctor the way the app titles them.
     */
    protected function doctorName($doctor): ?string
    {
        if (!$doctor) {
            return null;
        }

        $name = $doctor->fullname ?: trim($doctor->first_name . ' ' . $doctor->last_name);

        if ($name === '') {
            return null;
        }

        return str_starts_with(strtolower($name), 'dr') ? $name : 'Dr. ' . $name;
    }
}
