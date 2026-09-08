<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'checked_in_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * The appointment types accepted by the module.
     *
     * @var array<int, string>
     */
    public const TYPES = ['Consultation', 'Follow up'];

    /**
     * The visit types accepted by the module.
     *
     * @var array<int, string>
     */
    public const VISIT_TYPES = ['In patient', 'Out patient', 'Virtual', 'Tele consultation'];

    /**
     * The statuses an appointment can move through.
     *
     * @var array<int, string>
     */
    public const STATUSES = ['Scheduled', 'Checked In', 'Completed', 'Canceled', 'No show'];

    /**
     * Who put the appointment in the diary.
     *
     * @var array<int, string>
     */
    public const BOOKING_SOURCES = ['Hospital', 'Patient'];

    /**
     * The two choices the patient app opens its booking flow with, and the
     * visit type each one books as.
     *
     * @var array<string, string>
     */
    public const CONSULTATION_TYPES = [
        'in_person' => 'Out patient',
        'tele' => 'Tele consultation',
    ];

    /**
     * The visit types that are held over video rather than at the hospital.
     *
     * @var array<int, string>
     */
    public const VIRTUAL_VISIT_TYPES = ['Virtual', 'Tele consultation'];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function visit()
    {
        return $this->belongsTo(PatientVisit::class, 'visit_id', 'id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    /**
     * The doctor assigned to the appointment.
     *
     * Users live on the landlord connection, so this stays a plain belongsTo
     * (resolved with a separate query) and carries no database level foreign key.
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id', 'id');
    }

    /**
     * Limit the query to the appointments belonging to the given tenant.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $tenantUuid
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTenant(Builder $query, $tenantUuid)
    {
        return $query->when($tenantUuid, function ($q) use ($tenantUuid) {
            $q->where('tenant_uuid', $tenantUuid);
        });
    }

    /**
     * Limit the query to the appointments of one patient.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $patientId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPatient(Builder $query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * The moment the appointment actually starts.
     *
     * The date and the time are stored in separate columns because the admin
     * schedule reads and filters them separately; everything that has to reason
     * about "has this happened yet" wants them back together.
     *
     * @return \Carbon\Carbon|null
     */
    public function getStartsAtAttribute()
    {
        if (empty($this->date)) {
            return null;
        }

        try {
            return Carbon::parse($this->date->format('Y-m-d') . ' ' . ($this->time ?: '00:00:00'));
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Whether the appointment is held over video rather than at the hospital.
     */
    public function getIsVirtualAttribute(): bool
    {
        return in_array($this->visit_type, self::VIRTUAL_VISIT_TYPES, true);
    }

    /**
     * Whether the appointment still lies ahead of the patient.
     *
     * "Upcoming" is the first tab of the app's appointment list, and it is about
     * the appointment still being live rather than about the clock alone: an
     * appointment nobody closed off yesterday is history, and a cancelled one
     * belongs to its own tab however far away it is.
     */
    public function getIsUpcomingAttribute(): bool
    {
        if (in_array($this->status, ['Canceled', 'Completed', 'No show'], true)) {
            return false;
        }

        $startsAt = $this->starts_at;

        return $startsAt ? $startsAt->endOfDay()->isFuture() : false;
    }
}
