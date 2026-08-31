<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A priced hospital service that is billed outside of the pharmacy,
 * laboratory and radiology catalogues (admissions, consultations and the
 * general services a hospital charges for).
 */
class BillingService extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
        'status' => 'boolean',
    ];

    /**
     * The code the admission module bills against.
     */
    public const CODE_ADMISSION = 'ADMISSION';

    /**
     * The code the emergency admission module bills against.
     */
    public const CODE_EMERGENCY_ADMISSION = 'EMERGENCY_ADMISSION';

    /**
     * The categories a billing service can be grouped under.
     *
     * @var array<int, string>
     */
    public const CATEGORIES = ['Admission', 'Consultation', 'General'];

    public function serviceUnit()
    {
        return $this->belongsTo(ServiceUnit::class, 'service_unit_id');
    }

    /**
     * Limit the query to the billing services belonging to the given tenant.
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
     * Resolve an active billing service by its stable code.
     *
     * @param  string  $code
     * @param  string|null  $tenantId
     * @return static|null
     */
    public static function findByCode($code, $tenantId = null)
    {
        return static::query()
            ->forTenant($tenantId)
            ->where('code', $code)
            ->where('status', true)
            ->first();
    }
}
