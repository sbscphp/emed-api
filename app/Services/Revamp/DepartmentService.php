<?php

namespace App\Services\Revamp;

use App\Enums\ListModuleEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class DepartmentService
 *
 * This class provides services related to Department operations and acts as a
 * layer between the Controller and the Department model.
 */
class DepartmentService
{
    /**
     * Retrieve the departments of the current tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function overview($request)
    {
        $tenantUuid = $this->tenantUuid($request);

        $records = Department::query()
            ->forTenant($tenantUuid)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request['search_param'] . '%');
            })
            ->when($this->filled($request, 'status'), function ($query) use ($request) {
                $query->where('status', $this->toBoolean($request['status']));
            });

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    /**
     * Build the department counters of the current tenant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function stats($request)
    {
        $tenantUuid = $this->tenantUuid($request);
        $query = Department::query()->forTenant($tenantUuid);

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', true)->count(),
            'inactive' => (clone $query)->where('status', false)->count(),
        ];
    }

    /**
     * Create a department for the current tenant.
     *
     * @param  \App\Http\Requests\StoreDepartmentRequest  $request
     * @return \App\Models\Department
     */
    public function store(StoreDepartmentRequest $request)
    {
        $tenantUuid = $this->tenantUuid($request);
        $validated = $request->validated();

        $record = Department::create([
            'tenant_uuid' => $tenantUuid,
            'name' => $validated['name'],
            'status' => array_key_exists('status', $validated)
                ? $this->toBoolean($validated['status'])
                : true,
        ]);

        $this->log($record, 'Create', 'Department created successfully', sprintf(
            '%s created a new department: %s',
            $this->causerName(),
            $record->name
        ), [], $record->toArray());

        return $record;
    }

    /**
     * Retrieve a single department of the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\Department
     */
    public function show($id, $request = null)
    {
        return $this->findOrFail($id, $request);
    }

    /**
     * Update a department of the current tenant.
     *
     * @param  \App\Http\Requests\UpdateDepartmentRequest  $request
     * @param  int  $id
     * @return \App\Models\Department
     */
    public function update(UpdateDepartmentRequest $request, $id)
    {
        $department = $this->findOrFail($id, $request);
        $validated = $request->validated();
        $oldData = $department->toArray();

        if (array_key_exists('name', $validated)) {
            $department->name = $validated['name'];
        }

        if (array_key_exists('status', $validated)) {
            $department->status = $this->toBoolean($validated['status']);
        }

        $department->save();
        $department->refresh();

        $this->log($department, 'Update', 'Department updated successfully', sprintf(
            '%s updated the department: %s',
            $this->causerName(),
            $department->name
        ), $oldData, $department->toArray());

        return $department;
    }

    /**
     * Flip the active state of a department of the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\Department
     */
    public function toggleStatus($id, $request = null)
    {
        $department = $this->findOrFail($id, $request);
        $oldData = $department->toArray();

        $department->status = !$department->status;
        $department->save();
        $department->refresh();

        $this->log($department, 'Update', 'Department status toggled successfully', sprintf(
            '%s set the department %s to %s',
            $this->causerName(),
            $department->name,
            $department->status ? 'Active' : 'Inactive'
        ), $oldData, $department->toArray());

        return $department;
    }

    /**
     * Soft delete a department of the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\Department
     */
    public function destroy($id, $request = null)
    {
        $department = $this->findOrFail($id, $request);
        $oldData = $department->toArray();

        $department->delete();

        $this->log($department, 'Delete', 'Department deleted successfully', sprintf(
            '%s deleted the department: %s',
            $this->causerName(),
            $department->name
        ), $oldData, []);

        return $department;
    }

    /**
     * Export the given departments in the requested format.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator  $records
     * @param  string  $format
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export($records, $format)
    {
        $records = $records instanceof Collection ? $records : collect($records);

        $exportData = $records->map(function ($department) {
            return [
                'Name' => $department->name,
                'Status' => $department->status ? 'Active' : 'Inactive',
                'Date Created' => optional($department->created_at)->format('Y-m-d H:i'),
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'departments.csv');
        }

        if (strtolower($format) === 'pdf') {
            return ExportHelper::downloadPdf($exportData, 'departments.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    /**
     * Resolve a department scoped to the current tenant.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request|null  $request
     * @return \App\Models\Department
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function findOrFail($id, $request = null)
    {
        return Department::query()
            ->forTenant($this->tenantUuid($request))
            ->findOrFail($id);
    }

    /**
     * Write an audit log entry for a department action.
     *
     * @param  \App\Models\Department  $record
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
            'action_type' => "Models\Department",
            'log_name' => $logName,
            'description' => $description,
            'module_accessed' => ListModuleEnums::Department,
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
     * Resolve the tenant uuid carried by the request.
     *
     * @param  \Illuminate\Http\Request|null  $request
     * @return string|null
     */
    protected function tenantUuid($request = null)
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
