<?php

namespace App\Http\Resources\Patient;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single appointment as the patient app's "View Appointment Details" and
 * "Review your appointment" screens read it.
 *
 * The hospital block, the check in block and the action flags are attached by
 * PatientAppointmentService::decorate(); they are read here with a default so a
 * caller that skipped the decoration still gets a well shaped payload.
 */
class AppointmentDetailResource extends JsonResource
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
        $hospital = $this->hospital ?: [];

        return [
            'id' => $this->id,
            'reference_id' => $this->appointment_no,
            'doctor' => [
                'id' => optional($doctor)->id,
                'name' => $this->doctorName($doctor),
                'profile_picture' => optional($doctor)->profile_picture,
                'department' => optional($this->department)->name,
            ],
            'hospital' => [
                'name' => $hospital['name'] ?? null,
                'logo' => $hospital['logo'] ?? null,
                'location' => $hospital['address'] ?? null,
            ],
            'department' => [
                'id' => optional($this->department)->id,
                'name' => optional($this->department)->name,
            ],
            'date' => optional($this->date)->format('Y-m-d'),
            'date_label' => $startsAt ? $startsAt->format('F j') : null,
            'day_name' => optional($this->date)->format('l'),
            'time' => $this->formatTime($this->time),
            'date_and_time' => $startsAt ? $startsAt->format('D d M Y . h:i A') : null,
            'duration' => $this->duration,
            'consultation_type' => $this->consultationType(),
            'appointment_type' => $this->appointment_type,
            'visit_type' => $this->visit_type,
            'is_virtual' => (bool) $this->is_virtual,
            'meeting_platform' => $this->meeting_platform,
            'meeting_link' => $this->meeting_link,
            'reason' => $this->reason,
            'booking_status' => $this->bookingStatus(),
            'status' => $this->status,
            'booked_by' => $this->booking_source,
            'cancelled_at' => optional($this->cancelled_at)->toDateTimeString(),
            'cancellation_reason' => $this->cancellation_reason,
            'check_in' => $this->check_in ?: null,
            'actions' => $this->actions ?: null,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * The wording the detail screen puts on the "Booking Status" line.
     *
     * The column holds the hospital's own vocabulary; a patient reads
     * "Scheduled" as "not settled yet", so it is shown to them as confirmed.
     */
    protected function bookingStatus(): string
    {
        return match ($this->status) {
            'Scheduled' => 'Confirmed',
            'Checked In' => 'Checked in',
            default => (string) $this->status,
        };
    }

    /**
     * Which of the two cards the booking flow opened on produced this
     * appointment.
     */
    protected function consultationType(): string
    {
        return $this->is_virtual ? 'Virtual consultation' : 'In person consultation';
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
