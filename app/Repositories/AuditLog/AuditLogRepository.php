<?php

namespace App\Repositories\AuditLog;

use App\Helpers\ExportHelper;
use App\Models\AuditLog;
use App\Responser\JsonResponser;
use Carbon\Carbon;

class AuditLogRepository implements AuditLogInterface
{
    /**
     * Retrieve a collection of AuditLog from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return AuditLog::all();
    }


    /**
     * Create new AuditLog in the database.
     *
     * @param array $data
     * @return \App\Models\AuditLog
     */
    public function create(array $data)
    {
        return AuditLog::create($data);
    }


    /**
     * Update an existing AuditLog in the database.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\AuditLog
     */
    public function update(array $data, $id)
    {
        $record = AuditLog::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing AuditLog from the database.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = AuditLog::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing AuditLog in the database by their ID.
     *
     * @param int $id
     * @return \App\Models\AuditLog
     */
    public function find($id)
    {
        return AuditLog::find($id);
    }


    /**
     * Find an existing AuditLog in the database by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\AuditLog
     */
    public function findByAttribute($attr, $value)
    {
        return AuditLog::where($attr, $value)->first();
    }

    /**
     * Fetch all AuditLog fron the database
     *
     * @param \App\Models\AuditLog
     */
    public function getAllAuditLogs($search, $sortBy, $startDate, $endDate, $activityType, $paginate, $export = null, $module_accessed)
    {
        $query = AuditLog::with(['audit_log_transactions', 'causer']);

        if (isset($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('action_type', 'LIKE', '%' . $search . '%')
                    ->orWhere('log_name', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('causer', function ($q2) use ($search) {
                        $q2->where('fullname', 'LIKE', '%' . $search . '%');
                    });
            });
        }

        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }

        if (isset($activityType)) {
            $query->where('description', $activityType);
        }

        if (!empty($module_accessed)) {
            $query->where("module_accessed",  'LIKE', "%{$module_accessed}%");
        }

        if (isset($sortBy)) {
            $query->orderBy('created_at', $sortBy === 'oldest' ? 'ASC' : 'DESC');
        } else {
            $query->orderBy('created_at', 'DESC');
        }

        // Handle export (CSV or PDF)
        if (!empty($export) || $export === 'csv' || $export === 'pdf') {
            $logs = $query->get();

            $exportData = $logs->map(function ($log) {
                return [
                    'Action Type' => $log->action_type,
                    'Description' => $log->description,
                    'Log Name' => $log->log_name,
                    'Causer' => optional($log->causer)->fullname ?? 'System',
                    'Created At' => $log->created_at->toDateTimeString(),
                ];
            });

            if ($export === 'csv') {
                return ExportHelper::streamCsv($exportData->toArray(), null, 'audit-logs.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($exportData->toArray(), 'audit-logs.pdf');
            }

            return JsonResponser::send(true, 'Invalid export format specified', null, 400);
        }

        // Paginated or full result
        return $paginate ? $query->paginate(10) : $query->get();
    }
}
