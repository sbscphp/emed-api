<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A priced sub-service of a service — "General consultation" under GOPD — and
 * what the hospital bills outside of the pharmacy, laboratory and radiology
 * catalogues.
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

    /**
     * The service this sub-service is offered under.
     */
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * The department the sub-service is delivered in, where one is recorded.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

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
     * Limit the query to the sub-services of a given service.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int|null  $serviceId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForService(Builder $query, $serviceId)
    {
        return $query->when($serviceId, function ($q) use ($serviceId) {
            $q->where('service_id', $serviceId);
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
