<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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

    /**
     * The landlord user ids of the doctors assigned to this department.
     *
     * Not a relation: departments live on the tenant connection and users on the
     * landlord one, so the two cannot be joined in a single query. Callers take
     * these ids over to the landlord connection themselves.
     *
     * @return array<int, int>
     */
    public function doctorIds(): array
    {
        return DB::connection('tenant')
            ->table('department_user')
            ->where('department_id', $this->id)
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->all();
    }
}
