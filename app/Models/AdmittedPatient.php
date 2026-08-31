<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdmittedPatient extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];
    protected $connection = 'tenant';

    protected $casts = [
        'date_admitted' => 'date',
        'date_discharged' => 'date',
        'expected_admission_date' => 'date',
        'cancelled_at' => 'datetime',
        'deposit_amount' => 'decimal:2',
    ];

    /**
     * The admission types accepted by the module.
     *
     * @var array<int, string>
     */
    public const TYPES = ['Maternity', 'Surgery', 'Observation', 'Day case', 'Medical', 'Emergency', 'Other'];

    /**
     * The statuses an admission can move through.
     *
     * @var array<int, string>
     */
    public const STATUSES = ['Pending', 'Scheduled', 'Admitted', 'Discharged', 'Cancelled'];

    /**
     * The payment statuses an admission deposit can carry.
     *
     * @var array<int, string>
     */
    public const PAYMENT_STATUSES = ['Pending', 'Part Paid', 'Paid'];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function visit()
    {
        return $this->belongsTo(PatientVisit::class, 'visit_id');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    public function bedSpace()
    {
        return $this->belongsTo(Bed::class, 'bed_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * The doctor responsible for the admission.
     *
     * Users live on the landlord connection, so this stays a plain belongsTo
     * and carries no database level foreign key.
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function admittedBy()
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    public function dischargedBy()
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function billing()
    {
        return $this->belongsTo(BillingLog::class, 'billing_id');
    }

    /**
     * Limit the query to the admissions belonging to the given tenant.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $tenantId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTenant(Builder $query, $tenantId)
    {
        return $query->when($tenantId, function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        });
    }

    /**
     * The number of days the patient has stayed, or stayed for.
     *
     * @return int|null
     */
    public function getLengthOfStayAttribute()
    {
        if (!$this->date_admitted) {
            return null;
        }

        // Carbon 3 returns a signed float here, so it is floored to whole days.
        return (int) abs($this->date_admitted->diffInDays($this->date_discharged ?: now()));
    }

    /**
     * The ward and bed presented the way the admission table shows it.
     *
     * @return string|null
     */
    public function getWardBedAttribute()
    {
        $ward = optional($this->ward)->name;

        if (!$ward && !$this->bed) {
            return null;
        }

        return trim(implode('/ ', array_filter([$ward, $this->bed])));
    }
}
