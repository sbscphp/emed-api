<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Shapes the single appointment view.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $patient = $this->patient;
        $doctor = $this->doctor;
        $lastVisit = optional($patient)->visits_recent;

        return [
            'id' => $this->id,
            'appointment_no' => $this->appointment_no,
            'patient' => [
                'id' => optional($patient)->id,
                'name' => trim(optional($patient)->firstname . ' ' . optional($patient)->lastname),
                'age' => optional($patient)->age,
                'gender' => optional($patient)->gender,
                'patient_no' => optional($patient)->patientno,
                'card_no' => optional($patient)->cardno,
                'phone_number' => optional($patient)->phoneno,
                'last_visit' => optional(optional($lastVisit)->created_at)->toDateTimeString(),
            ],
            'department' => [
                'id' => optional($this->department)->id,
                'name' => optional($this->department)->name,
            ],
            'doctor' => [
                'id' => optional($doctor)->id,
                'name' => optional($doctor)->fullname
                    ?: trim(optional($doctor)->first_name . ' ' . optional($doctor)->last_name),
                'email' => optional($doctor)->email,
            ],
            'visit_id' => $this->visit_id,
            'appointment_type' => $this->appointment_type,
            'visit_type' => $this->visit_type,
            'date' => optional($this->date)->format('Y-m-d'),
            'time' => $this->formatTime($this->time),
            'date_and_time' => $this->dateAndTime(),
            'duration' => $this->duration,
            'reason' => $this->reason,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * Present the appointment moment the way the detail header reads it.
     *
     * @return string|null
     */
    protected function dateAndTime()
    {
        if (empty($this->date)) {
            return null;
        }

        try {
            $moment = Carbon::parse(
                $this->date->format('Y-m-d') . ' ' . ($this->time ?: '00:00:00')
            );

            return $moment->format('D, F j, Y . h:i A');
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Present a stored time column in the 12 hour format used by the view.
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
