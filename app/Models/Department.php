<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $guarded = ['id'];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Limit the query to the departments belonging to the given tenant.
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
