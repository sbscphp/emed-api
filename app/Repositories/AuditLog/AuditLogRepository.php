<?php

namespace App\Repositories\AuditLog;

use App\Models\AuditLog;
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
    public function getAllAuditLogs($search, $sortBy, $startDate, $endDate, $activityType, $paginate)
    {

        $query = AuditLog::with(['audit_log_transactions']);

        if(isset($search)){
            $query->where('action_type', 'LIKE', '%'.$search.'%')
                    ->orWhere('log_name', 'LIKE', '%'.$search.'%')
                    ->orWhereHas('causer', function($q) use ($search){
                        $q->where('fullname', 'LIKE', '%'.$search.'%');
                    });
        }

        if(isset($startdate) && isset($enddate)){
            $query->whereBetween('created_at',[Carbon::parse($startDate),Carbon::parse($endDate)]);
        }

        if(isset($activityType)){
            $query->where('description',$activityType);
        }

        if (isset($sortBy)) {
            switch ($sortBy) {
                case 'oldest':
                    $query->orderBy('created_at', 'ASC');
                    break;
                case 'recent':
                    $query->orderBy('created_at', 'DESC');
                    break;

                default:
                    $query->orderBy('created_at', 'DESC');
                    break;
            }
        }

        return $paginate ? $query->paginate(10) : $query->get();
    }
}
