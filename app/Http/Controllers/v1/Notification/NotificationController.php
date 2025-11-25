<?php

namespace App\Http\Controllers\v1\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tenant;
use App\Responser\JsonResponser;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(
        NotificationService $notificationService,
    ) {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        try {
            $overview = $this->notificationService->overview($request);

            return JsonResponser::send(false, 'Record(s) found successfully', $overview, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function markAsRead($id)
    {
        $notification = Notification::where('id', $id)->first();

        if (!$notification) {
            return JsonResponser::send(true, 'Notification not found.', [], 404);
        }

        $record = $this->notificationService->notification($notification);

        return JsonResponser::send(false, 'Notification marked as read.', $record, 200);
    }

    public function markAllAsRead(Request $request)
    {
        $user = Auth::user();
        $tenantId = $request->header('X-Tenant-ID');
        $tenant = Tenant::where('uuid', $tenantId)->first();

        // Mark all as read
        Notification::where('is_read', false)
            // ->where('type', $user->role)
            ->where('tenant_domain', $tenant->domain)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return JsonResponser::send(false, 'All notifications marked as read.', 200);
    }
}
