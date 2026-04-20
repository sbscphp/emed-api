<?php

namespace App\Services\SuperAdmin\AuditTrail;

use App\Models\LandlordAuditLog;

class AuditTrailService
{
    public function overview($request): array
    {
        return [
            'records'         => $this->logs($request),
        ];
    }

    /**
     * Get recent activity logs
     */
    private function logs($request)
    {
        $query = LandlordAuditLog::query()
            ->with(['causer' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'fullname', 'email')->with('superAdminRoles');
            }])
            ->when($request->search_param, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('action', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('log_name', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('module_accessed', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('description', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhereHas('causer', function ($q2) use ($request) {
                            $q2->where('fullname', 'LIKE', '%' . $request->search_param . '%')
                                ->orWhere('first_name', 'LIKE', '%' . $request->search_param . '%')
                                ->orWhere('id', intval($request->search_param));
                        });
                });
            })
            ->when($request->module_accessed, function ($query) use ($request) {
                $query->where('module_accessed', $request->module_accessed);
            })
            ->when($request->action, function ($query) use ($request) {
                $query->where('action', $request->action);
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when(($request->sort_by ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })
            ->when(($request->sort_by ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            });

        $paginate = $request->paginate ?? true;

        if ($paginate) {
            $paginated = $query->orderBy('id', 'DESC')->paginate($request->limit ?? 15);

            $paginated->getCollection()->transform(function ($log) {
                if ($log->causer) {
                    $role = $log->causer->superAdminRoles->first();
                    $log->causer->causerRole = $role;
                }
                return $log;
            });

            return $paginated;
        }

        $collection = $query->orderBy('id', 'DESC')->get();

        $collection->transform(function ($log) {
            if ($log->causer) {
                $role = $log->causer->superAdminRoles->first();
                $log->causer->causerRole = $role;
            }
            return $log;
        });

        return $collection;
    }
}
