<?php

namespace App\Services\Revamp;

use App\Enums\ListModuleEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Requests\StoreBillingServiceRequest;
use App\Http\Requests\UpdateBillingServiceRequest;
use App\Models\BillingService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Class BillingChargeService
 *
 * This class provides services related to the billing services catalogue — the
 * priced hospital services billed outside of the pharmacy, laboratory and
 * radiology catalogues — and acts as a layer between the Controller and the
 * BillingService model.
 */
class BillingChargeService
{
    /**
     * The relations every billing service payload is built from.
     *
     * @var array<int, string>
     */
    protected array $relations = ['service', 'department', 'serviceUnit'];

    /**
     * Retrieve the billing services of the current tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function overview($request)
    {
        $records = BillingService::query()
            ->forTenant($this->tenantId($request))
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $search = '%' . $request['search_param'] . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', $search)
                        ->orWhere('code', 'LIKE', $search)
                        ->orWhere('category', 'LIKE', $search)
                        ->orWhereRelation('service', 'name', 'LIKE', $search)
                        ->orWhereRelation('department', 'name', 'LIKE', $search)
                        ->orWhereRelation('serviceUnit', 'name', 'LIKE', $search);
                });
            })
            ->when(!empty($request['category']), function ($query) use ($request) {
                $query->where('category', $request['category']);
            })
            ->when(!empty($request['service_id']), function ($query) use ($request) {
                $query->where('service_id', $request['service_id']);
            })
            ->when(!empty($request['department_id']), function ($query) use ($request) {
                $query->where('department_id', $request['department_id']);
            })
            ->when(!empty($request['service_unit_id']), function ($query) use ($request) {
                $query->where('service_unit_id', $request['service_unit_id']);
            })
            ->when($this->filled($request, 'status'), function ($query) use ($request) {
                $query->where('status', $this->toBoolean($request['status']));
            })
            ->when(($request['sort_by'] ?? null) === 'name_ascending', function ($query) {
                $query->orderBy('name', 'ASC');
            })
            ->when(($request['sort_by'] ?? null) === 'name_descending', function ($query) {
                $query->orderBy('name', 'DESC');
            })
            ->when(($request['sort_by'] ?? null) === 'price_ascending', function ($query) {
                $query->orderBy('price', 'ASC');
            })
            ->when(($request['sort_by'] ?? null) === 'price_descending', function ($query) {
                $query->orderBy('price', 'DESC');
            })
            ->with($this->relations);

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    /**
     * Build the billing service counters of the current tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function stats($request)
    {
        $query = BillingService::query()->forTenant($this->tenantId($request));

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', true)->count(),
            'inactive' => (clone $query)->where('status', false)->count(),
            'categories' => (clone $query)->distinct()->count('category'),
            'services' => (clone $query)->distinct()->count('service_id'),
        ];
    }

    /**
     * Create a billing service for the current tenant.
     *
     * @param  \App\Http\Requests\StoreBillingServiceRequest  $request
     * @return \App\Models\BillingService
     */
    public function store(StoreBillingServiceRequest $request)
    {
        $tenantId = $this->tenantId($request);
        $validated = $request->validated();

        $record = BillingService::create([
            'tenant_id' => $tenantId,
            'service_id' => $validated['service_id'],
            'department_id' => $validated['department_id'] ?? null,
            'code' => !empty($validated['code'])
                ? strtoupper(trim($validated['code']))
                : $this->generateCode($validated['name'], $tenantId),
            'name' => $validated['name'],
            // The parent service is the grouping now; the legacy category is
            // kept in step with it unless one is posted explicitly.
            'category' => $validated['category']
                ?? optional(Service::find($validated['service_id']))->name,
            'service_unit_id' => $validated['service_unit_id'] ?? null,
            'price' => $validated['price'],
            'status' => array_key_exists('status', $validated)
                ? $this->toBoolean($validated['status'])
                : true,
        ]);

        $record->load($this->relations);

        $this->log($record, 'Create', 'Billing service created successfully', sprintf(
            '%s created a new billing service: %s',
            $this->causerName(),
            $record->name
        ), [], $record->toArray());

        return $record;
    }

    /**
     * Retrieve a single billing service of the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\BillingService
     */
    public function show($id, $request = null)
    {
        return $this->findOrFail($id, $request);
    }

    /**
     * Update a billing service of the current tenant.
     *
     * @param  \App\Http\Requests\UpdateBillingServiceRequest  $request
     * @param  int  $id
     * @return \App\Models\BillingService
     */
    public function update(UpdateBillingServiceRequest $request, $id)
    {
        $record = $this->findOrFail($id, $request);
        $validated = $request->validated();
        $oldData = $record->toArray();

        // Only the fields actually sent are touched.
        foreach (['service_id', 'department_id', 'name', 'category', 'service_unit_id', 'price'] as $field) {
            if (array_key_exists($field, $validated)) {
                $record->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('code', $validated)) {
            $record->code = strtoupper(trim($validated['code']));
        }

        if (array_key_exists('status', $validated)) {
            $record->status = $this->toBoolean($validated['status']);
        }

        $record->save();
        $record->refresh()->load($this->relations);

        $this->log($record, 'Update', 'Billing service updated successfully', sprintf(
            '%s updated the billing service: %s',
            $this->causerName(),
            $record->name
        ), $oldData, $record->toArray());

        return $record;
    }

    /**
     * Soft delete a billing service of the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\BillingService
     */
    public function destroy($id, $request = null)
    {
        $record = $this->findOrFail($id, $request);
        $oldData = $record->toArray();

        $record->delete();

        $this->log($record, 'Delete', 'Billing service deleted successfully', sprintf(
            '%s deleted the billing service: %s',
            $this->causerName(),
            $record->name
        ), $oldData, []);

        return $record;
    }

    /**
     * Export the given billing services in the requested format.
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
                'Code' => $service->code,
                'Name' => $service->name,
                'Service' => optional($service->service)->name,
                'Department' => optional($service->department)->name,
                'Category' => $service->category,
                'Service Unit' => optional($service->serviceUnit)->name,
                'Price' => $service->price,
                'Status' => $service->status ? 'Active' : 'Inactive',
                'Date Created' => optional($service->created_at)->format('Y-m-d H:i'),
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'billing_services.csv');
        }

        if (strtolower($format) === 'pdf') {
            return ExportHelper::downloadPdf($exportData, 'billing_services.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    /**
     * Resolve a billing service scoped to the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\BillingService
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function findOrFail($id, $request = null)
    {
        return BillingService::query()
            ->forTenant($this->tenantId($request))
            ->with($this->relations)
            ->findOrFail($id);
    }

    /**
     * Derive a stable lookup code from a service name, keeping it unique for
     * the tenant.
     *
     * @param  string  $name
     * @param  string|null  $tenantId
     * @return string
     */
    protected function generateCode($name, $tenantId)
    {
        $base = strtoupper(Str::snake(Str::ascii($name), '_'));
        $base = trim(preg_replace('/[^A-Z0-9]+/', '_', $base), '_') ?: 'SERVICE';
        $base = Str::limit($base, 90, '');

        $code = $base;
        $suffix = 1;

        while (BillingService::query()->forTenant($tenantId)->where('code', $code)->exists()) {
            $code = $base . '_' . (++$suffix);
        }

        return $code;
    }

    /**
     * Write an audit log entry for a billing service action.
     *
     * @param  \App\Models\BillingService  $record
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
            'action_type' => "Models\BillingService",
            'log_name' => $logName,
            'description' => $description,
            'module_accessed' => ListModuleEnums::BILLING,
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
