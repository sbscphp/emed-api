<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One notification, for hospital staff or for a patient.
 *
 * `audience` is what keeps the two apart — the hospital console lists Staff
 * rows and the patient app lists Patient ones — and `type` says what kind of
 * thing happened, which picks the icon and, with `data`, the record the app
 * opens when the row is tapped.
 */
class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $guarded = ['id'];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Who a row is for.
     */
    public const STAFF = 'Staff';

    public const PATIENT = 'Patient';

    /**
     * The kinds the patient app knows how to render and open.
     *
     * @var array<int, string>
     */
    public const PATIENT_TYPES = [
        'lab_result_ready',
        'radiology_result_ready',
        'payment_successful',
        'payment_failed',
        'payment_support_received',
        'payment_support_completed',
        'appointment_reminder',
        'appointment_update',
        'bill_created',
    ];

    /**
     * Rows the patient app should list.
     */
    public function scopeForPatient($query, int $userId)
    {
        return $query->where('audience', self::PATIENT)->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function getIsUnreadAttribute(): bool
    {
        return !filter_var($this->is_read, FILTER_VALIDATE_BOOLEAN);
    }
}
