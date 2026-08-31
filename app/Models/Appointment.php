<?php

namespace App\Models;

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
}
