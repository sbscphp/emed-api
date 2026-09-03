<?php

namespace App\Http\Resources\Patient;

use App\Enums\AppointmentStatusEnums;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the patient app's appointment list.
 *
 * The list is grouped under date headings and each row leads with a small date
 * badge, so the day and the short month are handed over already split rather
 * than left for the app to slice out of a timestamp.
 */
class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $doctor = $this->doctor;
        $startsAt = $this->starts_at;

        return [
            'id' => $this->id,
            'appointment_no' => $this->appointment_no,
            'doctor' => [
                'id' => optional($doctor)->id,
                'name' => $this->doctorName($doctor),
                'profile_picture' => optional($doctor)->profile_picture,
            ],
            'department' => [
                'id' => optional($this->department)->id,
                'name' => optional($this->department)->name,
            ],
            'date' => optional($this->date)->format('Y-m-d'),
            'day' => optional($this->date)->format('d'),
            'month' => optional($this->date)->format('M'),
            'day_name' => optional($this->date)->format('l'),
            'time' => $this->formatTime($this->time),
            'date_label' => $startsAt ? $startsAt->format('d M') : null,
            'appointment_type' => $this->appointment_type,
            'visit_type' => $this->visit_type,
            'is_virtual' => (bool) $this->is_virtual,
            'status' => $this->status,
            'tab' => $this->tab(),
        ];
    }

    /**
     * Which of the three tabs this appointment belongs under.
     */
    protected function tab(): string
    {
        if ($this->status === AppointmentStatusEnums::CANCELED->value) {
            return 'cancelled';
        }

        return $this->is_upcoming ? 'upcoming' : 'past';
    }

    /**
     * Present a doctor the way the app titles them.
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

    /**
     * Present a stored time column in the 12 hour format the app reads.
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
