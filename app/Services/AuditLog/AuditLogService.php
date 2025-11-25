<?php

namespace App\Services\AuditLog;

use App\Exports\AuditLogExport;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Repositories\AuditLog\AuditLogInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
        $tenantUuid = $request->header('X-Tenant-ID');

        $tenant = Tenant::where('uuid', $tenantUuid)->first();
        if (!$tenant) {
            throw new \Exception('Invalid tenant context.');
        }

        $tenant->makeCurrent();

        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);

        $records = AuditLog::query()
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
            ->when(!empty($request['role']), function ($query) use ($request, $tenantUuid) {

                $roleId = $request['role'];
                $tenantDb = DB::connection('tenant')->getDatabaseName();

                $query->whereExists(function ($sub) use ($tenantDb, $tenantUuid, $roleId) {
                    $sub->select(DB::raw(1))
                        ->from("$tenantDb.role_user")
                        ->join("$tenantDb.roles", "roles.id", "=", "role_user.role_id")
                        ->whereRaw("role_user.user_id = audit_logs.causer_id")
                        ->where("roles.tenant_id", $tenantUuid)
                        ->where("role_user.role_id", $roleId);
                });
            })
            ->when(!empty($request['module_accessed']), function ($query) use ($request) {
                $query->where('module_accessed', $request['module_accessed']);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })
            ->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })
            ->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            })
            ->with(['causer' => function ($q) use ($tenantUuid) {
                // Load basic causer info
                $q->select('id', 'first_name', 'last_name', 'fullname', 'email');
            }])
            ->with(['causer.roles' => function ($q) use ($tenantUuid) {
                // Load ONLY the tenant-specific roles
                $q->where('roles.tenant_id', $tenantUuid)
                    ->select('roles.id', 'roles.name', 'roles.display_name');
            }]);

        // Pagination
        if (!empty($request['paginate']) && empty($request['export'])) {
            $paginated = $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);

            // Transform: Attach single role to each causer
            $paginated->getCollection()->transform(function ($log) use ($tenantUuid) {
                if ($log->causer) {
                    $role = $log->causer->roles->first();
                    $log->causer->causerRole = $role;
                    unset($log->causer->roles);
                }
                return $log;
            });

            return $paginated;
        }

        // Non-paginated fallback
        $collection = $records->orderBy('id', 'DESC')->get();

        $collection->transform(function ($log) use ($tenantUuid) {
            if ($log->causer) {
                $role = $log->causer->roles->first();
                $log->causer->causerRole = $role;
                unset($log->causer->roles);
            }
            return $log;
        });

        return $collection;
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

    public function activityExport($records, $exportType = 'excel')
    {
        // Build export-ready array
        $exportData = $records->map(function ($log) {

            $role = $log->causer?->causerRole;

            return [
                'User ID'         => $log->causer->id ?? 'N/A',
                'User Role'       => $role->display_name ?? $role->name ?? 'N/A',
                'Timestamp'       => $log->created_at->toDateTimeString(),
                'Action Taken'    => $log->action,
                'Module Accessed' => $log->module_accessed,
            ];
        })->toArray();

        // Extract headers safely
        $headers = !empty($exportData) ? array_keys($exportData[0]) : [];

        // File name
        $fileName = 'audit_logs.' . strtolower($exportType);

        return match (strtolower($exportType)) {

            // ----------------------------
            // ✅ CSV EXPORT (Your style)
            // ----------------------------
            'csv' => ExportHelper::streamCsv(
                $exportData,
                $headers,
                'audit_logs.csv'
            ),

            // ----------------------------
            // ✅ EXCEL EXPORT
            // ----------------------------
            'excel' => Excel::download(
                new AuditLogExport(collect($exportData), $headers),
                'audit_logs.xlsx'
            ),

            // ----------------------------
            // ✅ PDF EXPORT
            // ----------------------------
            'pdf' => Pdf::loadView('exports.patients', [
                'patients' => $exportData
            ])->download('audit_logs.pdf'),

            default => throw new \Exception('Invalid export format.'),
        };
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
        $customDate = [];
        if ($validated->period === 'custom date' && $validated->start_date && $validated->end_date) {
            $customDate = [$validated->start_date, $validated->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($validated->period, $customDate);

        $query = AuditLog::with(['causer'])
            ->when($validated->startDate && $validated->endDate, function ($query) use ($validated) {
                $query->whereBetween('created_at', [$validated->start_date, $validated->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })
            ->when(($validated['sort_by'] ?? null) === 'date_ascending', function ($q) {
                $q->orderBy('created_at', 'ASC');
            })
            ->when(($validated['sort_by'] ?? null) === 'date_descending', function ($q) {
                $q->orderBy('created_at', 'DESC');
            });

        // Search
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('action_type', 'LIKE', "%$search%")
                    ->orWhere('log_name', 'LIKE', "%$search%")
                    ->orWhere('module_accessed', 'LIKE', "%$search%")
                    ->orWhere('action', 'LIKE', "%$search%")
                    ->orWhereHas('causer', function ($q2) use ($search) {
                        $q2->where('fullname', 'LIKE', "%$search%")
                            ->orWhere('id', intval($search));
                    });
            });
        }

        // Module filter
        if (!empty($validated['module_accessed'])) {
            $query->where('module_accessed', $validated['module_accessed']);
        }

        // Action filter
        if (!empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        // Pagination
        $paginate = $validated['paginate'] ?? false;

        return intval($paginate) === 1
            ? $query->orderBy('id', 'DESC')->paginate($validated['limit'] ?? 10)
            : $query->orderBy('id', 'DESC')->get();
    }
}
