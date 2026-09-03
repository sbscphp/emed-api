<?php

namespace App\Services\Patient\Dashboard;

use App\Services\Patient\Appointment\PatientAppointmentService;
use App\Services\Patient\PatientContextService;
use App\Services\Patient\Records\PatientRecordService;
use App\Services\Patient\Vitals\PatientVitalsService;

/**
 * Class PatientDashboardService
 *
 * The home screen of the patient mobile app.
 *
 * This module owns nothing of its own — the health snapshot belongs to vitals,
 * the tiles to the health record module and the card at the bottom to
 * appointments. What it owns is the arrangement: one request that fills a screen
 * made of four other modules, so the app opens on a single round trip instead of
 * four.
 */
class PatientDashboardService
{
    /**
     * The four measurements the health snapshot card shows, in its own order.
     *
     * @var array<int, string>
     */
    protected const SNAPSHOT_READINGS = ['blood_pressure', 'weight', 'pulse', 'blood_sugar'];

    public function __construct(
        protected PatientContextService $context,
        protected PatientVitalsService $vitals,
        protected PatientRecordService $records,
        protected PatientAppointmentService $appointments,
    ) {}

    /**
     * Everything the home screen paints.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return [
            'patient' => $this->header(),
            'health_snapshot' => $this->healthSnapshot(),
            'health_records' => $this->records->tiles(),
            'latest_appointment' => $this->latestAppointment(),
        ];
    }

    /**
     * The card at the bottom of the home screen, dressed by the appointment
     * module so "View Details" opens on exactly what this card summarised.
     *
     * @return \App\Models\Appointment|null
     */
    protected function latestAppointment()
    {
        $appointment = $this->appointments->latest();

        return $appointment ? $this->appointments->decorate($appointment) : null;
    }

    /**
     * The welcome line: who the patient is, at which hospital, and the number
     * printed on their card.
     *
     * @return array<string, mixed>
     */
    protected function header(): array
    {
        $patient = $this->context->patient();
        $user = $this->context->user();
        $tenant = $this->context->tenant();

        return [
            'id' => $patient->id,
            'first_name' => $patient->firstname,
            'last_name' => $patient->lastname,
            'full_name' => trim($patient->firstname . ' ' . $patient->lastname),
            'patient_no' => $patient->patientno,
            'card_no' => $patient->cardno,
            'gender' => $patient->gender,
            'profile_picture' => $user->profile_picture,
            'hospital' => [
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'logo' => $tenant->logo,
                'address' => $tenant->address,
            ],
        ];
    }

    /**
     * The health snapshot card: four of the six readings from the patient's most
     * recent sitting, and when that was.
     *
     * @return array<string, mixed>
     */
    protected function healthSnapshot(): array
    {
        $latest = $this->vitals->latest();

        if (!$latest) {
            return [
                'has_readings' => false,
                'readings' => [],
                'last_updated' => null,
                'recorded_at' => null,
                'vitals_id' => null,
            ];
        }

        return [
            'has_readings' => true,
            'readings' => $this->vitals->readingsFor($latest, self::SNAPSHOT_READINGS),
            'last_updated' => optional($latest->created_at)->format('d F Y'),
            'recorded_at' => optional($latest->created_at)->toDateTimeString(),
            // So "View More" can open straight onto the sitting it summarised.
            'vitals_id' => $latest->id,
        ];
    }
}
