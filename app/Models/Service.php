<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A service a hospital offers — GOPD, SOPD, Antenatal, Radiology — and the
 * parent every priced sub-service (a billing service) hangs off.
 */
class Service extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $connection = 'tenant';
    protected $table = 'services';

    protected $casts = [
        'price' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function visits()
    {
        return $this->hasMany(PatientVisit::class, 'service_id', 'id');
    }

    public function billing()
    {
        return $this->hasMany(BillingLog::class, 'service_type_id');
    }

    /**
     * The priced sub-services offered under this service.
     */
    public function subServices()
    {
        return $this->hasMany(BillingService::class, 'service_id');
    }

    /**
     * Limit the query to the services belonging to the given tenant.
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
}
