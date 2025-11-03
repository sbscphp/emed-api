<?php

namespace App\Services\AuditLog;

use App\Exports\AuditLogExport;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\AuditLog;
use App\Repositories\AuditLog\AuditLogInterface;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Class AuditLogService
 *
 * This class provides services related to AuditLog operations and acts as a
 * layer between the Controller and the AuditLogRepository.
 */
class AuditLogService
{
    protected AuditLogInterface $AuditLogInterface;
    /**
     * AuditLog constructor.
     *
     * @param AuditLogInterface $AuditLogInterface
     */
    public function __construct(AuditLogInterface $AuditLogInterface)
    {
        $this->AuditLogInterface = $AuditLogInterface;
    }

    public function activityOverview($request)
    {
        $period = $request->input('period');
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');
        $tenantId = $request->header('X-Tenant-ID');

        $customDate = [];
        if ($period === 'custom date' && $startDateInput && $endDateInput) {
            $customDate = [$startDateInput, $endDateInput];
        }

        $dateFilter = GeneralHelper::dateFilter($period, $customDate);
        $records = AuditLog::query()
            // ->where('tenant_id', $tenantId)
            ->whereIn('module_accessed', ['Billing', 'Records', 'Pharmacy'])
            ->when(!empty($request->search), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('action', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('log_name', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('module_accessed', 'LIKE', '%' . $request->search . '%')
                        ->orWhereHas('causer', function ($q2) use ($request) {
                            $q2->where('fullname', 'LIKE', '%' . $request->search . '%')
                                ->orWhere('id', intval($request->search));
                        });
                });
            })
            ->when(!empty($request['role']), function ($query) use ($request) {
                $query->whereHas('causer.roles', function ($q) use ($request) {
                    $q->where('name', $request['role']);
                });
            })
            ->when(!empty($request['module_accessed']), function ($query) use ($request) {
                $query->where('module_accessed', $request['module_accessed']);
            })
            ->when(!empty($request['start_date']) && !empty($request['end_date']), function ($query) use ($request) {
                $query->whereBetween('created_at', [$request['start_date'], $request['end_date']]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                $query->whereBetween('created_at', $dateFilter);
            })
            ->when(($request['sortBy'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })
            ->when(($request['sortBy'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            })
            ->with([
                'audit_log_transactions',
                'causer.roles' => function ($q) use ($tenantId) {
                    // Only load roles belonging to the current tenant
                    $q->where('roles.tenant_id', $tenantId)
                        ->select('roles.id', 'roles.name', 'roles.display_name', 'roles.tenant_id');
                },
            ]);

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function activityStats($request)
    {
        $period = $request->input('period');
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        $customDate = [];
        if ($period === 'custom date' && $startDateInput && $endDateInput) {
            $customDate = [$startDateInput, $endDateInput];
        }

        $dateFilter = GeneralHelper::dateFilter($period, $customDate);

        // Main query (optionally filtered)
        $query = AuditLog::query()
            ->whereIn('module_accessed', ['Billing', 'Records', 'Pharmacy'])
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            });

        $total = (clone $query)->count();
        return [
            'totalUserActivity' => $total,
        ];
    }

    public function activityExport($records)
    {
        $recordHeadings = ['User ID', 'User Role', 'Timestamp', 'Action Taken', 'Module Accessed'];
        $records = $records->map(function ($record) {
            return [
                $record->causer->id ?? 'N/A',
                $record->causer->role_names ?? 'N/A',
                $record->created_at->toDateTimeString(),
                $record->action,
                $record->module_accessed,
            ];
        });
        return Excel::download(new AuditLogExport($records, $recordHeadings), 'user_activity.xlsx');
    }


    /**
     * Retrieve all AuditLog.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->AuditLogInterface->all();
    }

    /**
     * Create a new AuditLog using the data provided.
     *
     * @param array $data
     * @return \App\Models\AuditLog
     */
    public function create(array $data)
    {
        return $this->AuditLogInterface->create($data);
    }


    /**
     * Update an existing AuditLog with the provided data.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\AuditLog
     */
    public function update(array $data, $id)
    {
        return $this->AuditLogInterface->update($data, $id);
    }


    /**
     * Delete a AuditLog by heir ID.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->AuditLogInterface->delete($id);
    }


    /**
     * Find a AuditLog by their ID.
     *
     * @param int $id
     * @return \App\Models\AuditLog
     */
    public function find($id)
    {
        return $this->AuditLogInterface->find($id);
    }


    /**
     * Find an existing AuditLog  by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\AuditLog
     */
    public function findByAttribute($attr, $value)
    {
        return $this->AuditLogInterface->findByAttribute($attr, $value);
    }

    /**
     * Fetch all AuditLog fron the database
     *
     * @param \App\Models\AuditLog
     */
    public function getAllAuditLogs($search, $sortBy, $startDate, $endDate, $activityType, $paginate, $export,   $action, $module_accessed)
    {
        return $this->AuditLogInterface->getAllAuditLogs($search, $sortBy, $startDate, $endDate, $activityType, $paginate, $export, $action, $module_accessed);
    }


    public function data_changes($validated)
    {
        //   AuditLog
        $query = AuditLog::whereIn('module_accessed', ['Billing', 'Records', 'Pharmacy'])->with(['audit_log_transactions', 'causer']);

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('action_type', 'LIKE', '%' . $search . '%')
                    ->orWhere('log_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('module_accessed', 'LIKE', '%' . $search . '%')
                    ->orWhere('action', 'LIKE', '%' . $search . '%')

                    ->orWhereHas('causer', function ($q2) use ($search) {
                        $q2->where('fullname', 'LIKE', '%' . $search . '%')
                            ->orWhere('id',  intval($search));
                    });
            });
        }


        if (!empty($validated['module_accessed'])) {
            $query->where('module_accessed', $validated['module_accessed']);
        }

        if (!empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }


        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $query->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }


        $paginate =  $validated['paginate'] ?? false;
        return intval($paginate) == 1 ? $query->orderBy('id', 'DESC')->paginate(10) : $query->orderBy('id', 'DESC')->get();
    }
}
