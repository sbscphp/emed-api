<?php

namespace App\Services\AuditLog;

use App\Helpers\ExportHelper;
use App\Models\AuditLog;
use App\Repositories\AuditLog\AuditLogInterface;
use Carbon\Carbon;

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
        return intval($paginate) == 1 ? $query->paginate(10) : $query->get();
    }
}
