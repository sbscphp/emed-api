<?php

namespace App\Services\Notification;

use App\Enums\GeneralEnums;
use App\Helpers\GeneralHelper;
use App\Helpers\UserMgtHelper;
use App\Models\Department;
use App\Models\EmployeeDetail;
use App\Models\Notification;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

/**
 * Class NotificationService
 * 
 * This class provides services related to User operations and acts as a 
 * layer between the controller and the UserRepository.
 */
class NotificationService
{
    public function overview($request)
    {
        $currentUser = Auth::user();
        $tenant = $currentUser->tenant;
        // Extract custom date range if available
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);

        $records = Notification::query()
            ->where('tenant_domain', $tenant->domain)
            ->when($request->search_param, function ($query) use ($request) {
                $query->where('title', 'LIKE', '%' . $request->search_param . '%');
            })
            ->when($request->is_read, function ($query) use ($request) {
                $query->where('is_read', $request->is_read);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->where('created_at', '>=', $dateFilter);
            })
            ->when($request->sortBy == 'alphabetically', function ($query) {
                $query->orderBy('title', 'ASC');
            });

        return $records->paginate($request->limit);
    }

    public function notification($notification)
    {
        $notification->update([
            'is_read' => "true",
            'read_at' => now()
        ]);

        return $notification->fresh();
    }

}
