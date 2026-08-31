<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Shapes a single row of the appointment schedule table.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $patient = $this->patient;
        $doctor = $this->doctor;

        return [
            'id' => $this->id,
            'appointment_no' => $this->appointment_no,
            'date' => optional($this->date)->format('Y-m-d'),
            'time' => $this->formatTime($this->time),
            'patient' => [
                'id' => optional($patient)->id,
                'name' => trim(optional($patient)->firstname . ' ' . optional($patient)->lastname),
                'patient_no' => optional($patient)->patientno,
                'card_no' => optional($patient)->cardno,
            ],
            'doctor' => [
                'id' => optional($doctor)->id,
                'name' => optional($doctor)->fullname
                    ?: trim(optional($doctor)->first_name . ' ' . optional($doctor)->last_name),
            ],
            'department' => [
                'id' => optional($this->department)->id,
                'name' => optional($this->department)->name,
            ],
            'appointment_type' => $this->appointment_type,
            'visit_type' => $this->visit_type,
            'status' => $this->status,
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }

    /**
     * Present a stored time column in the 12 hour format used by the schedule.
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
            return \Carbon\Carbon::parse($time)->format('h:i A');
        } catch (\Throwable $th) {
            return $time;
        }
    }
}
