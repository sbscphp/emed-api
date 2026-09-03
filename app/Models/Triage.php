<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Triage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $casts = [
        'blood_pressure' => 'array',
    ];


    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit()
    {
        return $this->belongsTo(PatientVisit::class, 'visit_id');
    }

    /**
     * The staff member who took the readings.
     *
     * Users live on the landlord connection, so this stays a plain belongsTo
     * (resolved with a separate query) and carries no database level foreign key.
     */
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Limit the query to the vitals belonging to the given tenant.
     *
     * The column is `tenant_id` here rather than the `tenant_uuid` the newer
     * tables use, and it holds the tenant uuid despite the name.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $tenantUuid
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTenant(Builder $query, $tenantUuid)
    {
        return $query->when($tenantUuid, function ($q) use ($tenantUuid) {
            $q->where('tenant_id', $tenantUuid);
        });
    }

    /**
     * Limit the query to the vitals of one patient.
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
     * The systolic and diastolic pair of this reading.
     *
     * The column is a json list of {systolic, diastolic} objects because the
     * triage form allows more than one measurement in a sitting. The last entry
     * is the one that stands.
     *
     * @return array{systolic: int|null, diastolic: int|null}
     */
    public function getBloodPressureReadingAttribute(): array
    {
        $readings = array_values(array_filter((array) $this->blood_pressure, 'is_array'));

        if (empty($readings)) {
            return ['systolic' => null, 'diastolic' => null];
        }

        $last = end($readings);

        return [
            'systolic' => isset($last['systolic']) ? (int) $last['systolic'] : null,
            'diastolic' => isset($last['diastolic']) ? (int) $last['diastolic'] : null,
        ];
    }
}
