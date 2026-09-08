<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Notification\NotificationIndexRequest;
use App\Http\Resources\Patient\NotificationResource;
use App\Responser\JsonResponser;
use App\Services\Patient\Notification\PatientNotificationService;
use Throwable;

/**
 * The notification module of the patient mobile app.
 *
 * Read only — rows are written by whichever module had something to tell the
 * patient: the lab releasing a result, a payment confirming, a friend
 * contributing to a bill, an appointment coming up.
 *
 * Distinct from the hospital console's own notification endpoints, which list
 * the Staff rows in the same table. The two audiences never see each other's.
 */
class NotificationController extends Controller
{
    public function __construct(protected PatientNotificationService $notificationService) {}

    /**
     * GET /v1/patient/notifications
     *
     * The list behind the bell, already grouped into Today / Yesterday /
     * Earlier, with the unread count for the badge.
     */
    public function index(NotificationIndexRequest $request)
    {
        try {
            $result = $this->notificationService->index($request);

            $data = ['unread_count' => $result['unread_count']];

            if (isset($result['groups'])) {
                $data['groups'] = collect($result['groups'])->map(fn($group) => [
                    'label' => $group['label'],
                    'count' => $group['count'],
                    'records' => NotificationResource::collection(collect($group['records'])),
                ]);

                $data['records'] = NotificationResource::collection($result['records']);
            } else {
                $data['records'] = NotificationResource::collection($result['records'])
                    ->response()
                    ->getData(true);
            }

            return JsonResponser::send(false, 'Notifications retrieved successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/notifications/unread-count
     *
     * Just the badge, for the app to poll cheaply.
     */
    public function unreadCount()
    {
        try {
            return JsonResponser::send(false, 'Unread count retrieved successfully.', [
                'unread_count' => $this->notificationService->unreadCount(),
            ], 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/notifications/{id}
     *
     * One notification opened. Opening it marks it read — the screen has no
     * separate action for that.
     */
    public function show($id)
    {
        try {
            $notification = $this->notificationService->show($id);

            return JsonResponser::send(
                false,
                'Notification retrieved successfully.',
                new NotificationResource($notification),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/notifications/{id}/read
     */
    public function markAsRead($id)
    {
        try {
            $notification = $this->notificationService->read($id);

            return JsonResponser::send(
                false,
                'Notification marked as read.',
                new NotificationResource($notification),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * PUT /v1/patient/notifications/read-all
     */
    public function markAllAsRead()
    {
        try {
            $count = $this->notificationService->readAll();

            return JsonResponser::send(false, 'All notifications marked as read.', [
                'marked' => $count,
                'unread_count' => 0,
            ], 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
