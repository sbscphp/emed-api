<?php

namespace App\Services\Notification;

use App\Helpers\GeneralHelper;
use App\Models\Notification;
use App\Models\Tenant;
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
        $tenantId = $request->header('X-Tenant-ID');
        $tenant = Tenant::where('uuid', $tenantId)->first();

        // Extract custom date range if available
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);

        // --------------------------------------------------------------------
        // 🔥 Base query for all filters (used for both listing & unread count)
        // --------------------------------------------------------------------
        $baseQuery = Notification::query()
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
            });

        // -----------------------------
        // 🔥 Get unread notification count
        // -----------------------------
        $unreadCount = (clone $baseQuery)
            ->where('is_read', false)
            ->count();

        // -----------------------------
        // 🔥 Apply sorting for listing
        // -----------------------------
        $records = (clone $baseQuery)
            ->when($request->sortBy == 'alphabetically', function ($query) {
                $query->orderBy('title', 'ASC');
            })
            ->orderBy('id', 'DESC')->paginate($request->limit);

        // -----------------------------
        // 🔥 Add unread count to the response
        // -----------------------------
        return [
            'unread_notification_count' => $unreadCount,
            'data' => $records
        ];
    }

    public function notification($notification)
    {
        $notification->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        return $notification->fresh();
    }
}
