<?php

namespace App\Services\Revamp;

use App\Enums\ListModuleEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\BillingService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class ServiceCatalogService
 *
 * This class provides services related to the services catalogue — the
 * hospital's own services (GOPD, SOPD, Antenatal) that the priced
 * sub-services hang off — and acts as a layer between the Controller and the
 * Service model.
 */
class ServiceCatalogService
{
    /**
     * The aggregates every service row is presented with: how many
     * sub-services it holds and what they cost.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function withSubServiceAggregates($query)
    {
        return $query
            ->withCount('subServices')
            ->withMin('subServices', 'price')
            ->withMax('subServices', 'price');
    }

    /**
     * Retrieve the services of the current tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function overview($request)
    {
        $records = $this->withSubServiceAggregates(
            Service::query()->forTenant($this->tenantId($request))
        )
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $search = '%' . $request['search_param'] . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', $search)
                        ->orWhereRelation('subServices', 'name', 'LIKE', $search);
                });
            })
            ->when($this->filled($request, 'status'), function ($query) use ($request) {
                $query->where('status', $this->toBoolean($request['status']));
            })
            ->tap(fn($query) => $this->applyDateRange($query, $request))
            ->when(($request['sort_by'] ?? null) === 'name_ascending', fn($q) => $q->orderBy('name', 'ASC'))
            ->when(($request['sort_by'] ?? null) === 'name_descending', fn($q) => $q->orderBy('name', 'DESC'))
            ->when(($request['sort_by'] ?? null) === 'price_ascending', fn($q) => $q->orderBy('price', 'ASC'))
            ->when(($request['sort_by'] ?? null) === 'price_descending', fn($q) => $q->orderBy('price', 'DESC'));

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    /**
     * Build the counters shown above the services table.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function stats($request)
    {
        $tenantId = $this->tenantId($request);
        $query = Service::query()->forTenant($tenantId);

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', true)->count(),
            'inactive' => (clone $query)->where('status', false)->count(),
            'sub_services' => BillingService::query()->forTenant($tenantId)->count(),
        ];
    }

    /**
     * Create a service for the current tenant.
     *
     * @param  \App\Http\Requests\StoreServiceRequest  $request
     * @return \App\Models\Service
     */
    public function store(StoreServiceRequest $request)
    {
        $validated = $request->validated();

        $record = Service::create([
            'tenant_id' => $this->tenantId($request),
            'name' => trim($validated['name']),
            'price' => $validated['price'] ?? 0.00,
            'status' => array_key_exists('status', $validated)
                ? $this->toBoolean($validated['status'])
                : true,
        ]);

        $this->log($record, 'Create', 'Service created successfully', sprintf(
            '%s created a new service: %s',
            $this->causerName(),
            $record->name
        ), [], $record->toArray());

        return $this->findOrFail($record->id, $request);
    }

    /**
     * Retrieve a single service of the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\Service
     */
    public function show($id, $request = null)
    {
        return $this->findOrFail($id, $request);
    }

    /**
     * The sub-services offered under one service of the current tenant.
     *
     * Filterable by department and by the date range the sub-services were
     * added in, which is what the Department column and the Filter date button
     * on the service details screen post back.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function subServices($id, $request)
    {
        $service = $this->findOrFail($id, $request);

        $records = BillingService::query()
            ->forTenant($this->tenantId($request))
            ->forService($service->id)
            ->with(['service', 'department', 'serviceUnit'])
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $search = '%' . $request['search_param'] . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', $search)
                        ->orWhere('code', 'LIKE', $search)
                        ->orWhereRelation('department', 'name', 'LIKE', $search);
                });
            })
            ->when(!empty($request['department_id']), function ($query) use ($request) {
                $query->whereIn('department_id', $this->idList($request['department_id']));
            })
            ->when($this->filled($request, 'status'), function ($query) use ($request) {
                $query->where('status', $this->toBoolean($request['status']));
            })
            ->tap(fn($query) => $this->applyDateRange($query, $request));

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    /**
     * Update a service of the current tenant.
     *
     * @param  \App\Http\Requests\UpdateServiceRequest  $request
     * @param  int  $id
     * @return \App\Models\Service
     */
    public function update(UpdateServiceRequest $request, $id)
    {
        $record = $this->findOrFail($id, $request);
        $validated = $request->validated();
        $oldData = $record->toArray();

        if (array_key_exists('name', $validated)) {
            $record->name = trim($validated['name']);
        }

        if (array_key_exists('price', $validated)) {
            $record->price = $validated['price'] ?? 0.00;
        }

        if (array_key_exists('status', $validated)) {
            $record->status = $this->toBoolean($validated['status']);
        }

        $record->save();

        $this->log($record, 'Update', 'Service updated successfully', sprintf(
            '%s updated the service: %s',
            $this->causerName(),
            $record->name
        ), $oldData, $record->toArray());

        return $this->findOrFail($record->id, $request);
    }

    /**
     * Flip the active state of a service of the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\Service
     */
    public function toggleStatus($id, $request = null)
    {
        $record = $this->findOrFail($id, $request);
        $oldData = $record->toArray();

        $record->status = !$record->status;
        $record->save();

        $this->log($record, 'Update', 'Service status toggled successfully', sprintf(
            '%s set the service %s to %s',
            $this->causerName(),
            $record->name,
            $record->status ? 'Active' : 'Inactive'
        ), $oldData, $record->toArray());

        return $this->findOrFail($record->id, $request);
    }

    /**
     * Soft delete a service of the current tenant, together with the
     * sub-services that would otherwise be left without a parent.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\Service
     */
    public function destroy($id, $request = null)
    {
        $record = $this->findOrFail($id, $request);
        $oldData = $record->toArray();

        DB::connection('tenant')->transaction(function () use ($record) {
            BillingService::query()->where('service_id', $record->id)->delete();
            $record->delete();
        });

        $this->log($record, 'Delete', 'Service deleted successfully', sprintf(
            '%s deleted the service: %s',
            $this->causerName(),
            $record->name
        ), $oldData, []);

        return $record;
    }

    /**
     * Export the given services in the requested format.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator  $records
     * @param  string  $format
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export($records, $format)
    {
        $records = $records instanceof Collection ? $records : collect($records);

        $exportData = $records->map(function ($service) {
            return [
                'Service' => $service->name,
                'Sub Services' => (int) ($service->sub_services_count ?? 0),
                'Price' => $service->sub_services_min_price ?? $service->price,
                'Status' => $service->status ? 'Active' : 'Inactive',
                'Date Created' => optional($service->created_at)->format('Y-m-d H:i'),
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'services.csv');
        }

        if (strtolower($format) === 'pdf') {
            return ExportHelper::downloadPdf($exportData, 'services.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    /**
     * Resolve a service scoped to the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\Service
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function findOrFail($id, $request = null)
    {
        return $this->withSubServiceAggregates(
            Service::query()->forTenant($this->tenantId($request))
        )->findOrFail($id);
    }

    /**
     * Write an audit log entry for a service action.
     *
     * @param  \App\Models\Service  $record
     * @param  string  $action
     * @param  string  $logName
     * @param  string  $description
     * @param  array  $oldData
     * @param  array  $newData
     * @return void
     */
    protected function log($record, $action, $logName, $description, array $oldData = [], array $newData = [])
    {
        $currentUser = GeneralHelper::userInstance();

        GeneralHelper::storeAuditLog([
            'causer_id' => optional($currentUser)->id,
            'action_id' => $record->id,
            'action' => $action,
            'action_type' => "Models\Service",
            'log_name' => $logName,
            'description' => $description,
            'module_accessed' => ListModuleEnums::Service,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }

    /**
     * Present the acting user in an audit log description.
     *
     * @return string
     */
    protected function causerName()
    {
        $user = GeneralHelper::userInstance();

        if (!$user) {
            return 'System';
        }

        $name = $user->fullname ?: trim($user->first_name . ' ' . $user->last_name);

        return $name !== '' ? $name : ($user->email ?? 'System');
    }

    /**
     * Resolve the tenant identifier carried by the request.
     *
     * @param  \Illuminate\Http\Request|null  $request
     * @return string|null
     */
    protected function tenantId($request = null)
    {
        $request = $request ?: request();

        return $request->header('X-Tenant-ID');
    }

    /**
     * The ids a filter was given, whether it arrived as one id, a comma
     * separated list or an array — a multi-select posts any of the three.
     *
     * @param  mixed  $value
     * @return array<int, int>
     */
    protected function idList($value)
    {
        $ids = is_array($value) ? $value : explode(',', (string) $value);

        $ids = array_map(fn($id) => (int) trim((string) $id), $ids);

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Narrow a query to the date range the Filter date control posts.
     *
     * Either end may be given on its own: a start alone reads as "from this
     * day", an end alone as "up to this day". Both ends are inclusive of the
     * whole day, so a same-day filter still returns that day's records.
     *
     * @param  \Illuminate\Contracts\Database\Query\Builder  $query
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $column
     * @return void
     */
    protected function applyDateRange($query, $request, $column = 'created_at')
    {
        $start = $this->startOfDay($request['start_date'] ?? null);
        $end = $this->endOfDay($request['end_date'] ?? null);

        if ($start && $end) {
            $query->whereBetween($column, [$start, $end]);

            return;
        }

        if ($start) {
            $query->where($column, '>=', $start);
        }

        if ($end) {
            $query->where($column, '<=', $end);
        }
    }

    /**
     * The first moment of a posted date, or null when it is absent or
     * unreadable — a filter nobody can parse should not silently empty a table.
     *
     * @param  mixed  $value
     * @return string|null
     */
    protected function startOfDay($value)
    {
        $timestamp = empty($value) ? false : strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d 00:00:00', $timestamp);
    }

    /**
     * The last moment of a posted date.
     *
     * @param  mixed  $value
     * @return string|null
     */
    protected function endOfDay($value)
    {
        $timestamp = empty($value) ? false : strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d 23:59:59', $timestamp);
    }

    /**
     * Determine whether a filter was supplied on the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $key
     * @return bool
     */
    protected function filled($request, $key)
    {
        return isset($request[$key]) && $request[$key] !== '';
    }

    /**
     * Normalize a truthy request value into a boolean.
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function toBoolean($value)
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
