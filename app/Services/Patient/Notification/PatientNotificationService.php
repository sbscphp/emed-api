<?php

namespace App\Services\Patient\Notification;

use App\Exceptions\PatientAppException;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Tenant;
use App\Services\Patient\PatientContextService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Class PatientNotificationService
 *
 * The patient app's notification screen, and the one way the rest of the app
 * writes to it.
 *
 * Two things separate these rows from the ones the hospital console lists. They
 * are addressed to a patient rather than to a role, which `audience` carries;
 * and each one opens something — a released result, an invoice, an appointment —
 * which `type` and `data` carry between them. Nothing here composes a deep link
 * itself: the app owns its own routing, so a row says which kind of record and
 * which id, and the app decides what screen that is.
 *
 * Writing a notification must never bring down the thing that caused it. A
 * result is released whether or not the row was written, so every write is
 * wrapped and logged rather than allowed to throw.
 */
class PatientNotificationService
{
    public function __construct(protected PatientContextService $context) {}

    /**
     * The list behind the bell, grouped the way the screen groups it.
     *
     * Today / Yesterday / Earlier are worked out here rather than in the app so
     * that the boundaries follow the hospital's day rather than the phone's
     * timezone.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function index($request): array
    {
        $user = $this->context->user();

        $query = Notification::query()
            ->forPatient($user->id)
            ->when(!empty($request['type']), fn($q) => $q->where('type', $request['type']))
            ->when(
                $request['is_read'] !== null && $request['is_read'] !== '',
                fn($q) => $q->where('is_read', filter_var($request['is_read'], FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC');

        $unread = Notification::query()->forPatient($user->id)->unread()->count();

        if (!empty($request['paginate'])) {
            return [
                'unread_count' => $unread,
                'records' => $query->paginate($request['limit'] ?? 20),
            ];
        }

        $records = $query->limit($request['limit'] ?? 50)->get();

        return [
            'unread_count' => $unread,
            'records' => $records,
            'groups' => $this->group($records),
        ];
    }

    /**
     * One notification, opened.
     *
     * Reading it marks it read — the screen has no separate action for that, and
     * a row the patient has just looked at should not still be counted unread.
     *
     * @throws \App\Exceptions\PatientAppException
     */
    public function show($id): Notification
    {
        $notification = Notification::query()
            ->forPatient($this->context->user()->id)
            ->find($id);

        if (!$notification) {
            throw new PatientAppException('We could not find that notification.', 404);
        }

        return $this->markAsRead($notification);
    }

    /**
     * @throws \App\Exceptions\PatientAppException
     */
    public function read($id): Notification
    {
        return $this->show($id);
    }

    /**
     * Clear the badge in one go.
     */
    public function readAll(): int
    {
        return Notification::query()
            ->forPatient($this->context->user()->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * How many rows are waiting, for the dot on the bell.
     */
    public function unreadCount(): int
    {
        return Notification::query()->forPatient($this->context->user()->id)->unread()->count();
    }

    /**
     * Write a notification for a patient.
     *
     * The entry point for every module that has something to tell a patient.
     * Takes the tenant and patient explicitly rather than reading the request
     * context, because most callers are hospital-side — a lab releasing a result
     * is acting on a staff request, not on the patient's.
     *
     * Returns null rather than throwing when it cannot write: the caller's own
     * work has already succeeded by the time it gets here.
     *
     * @param  array<string, mixed>  $data
     */
    public function notify(
        Tenant $tenant,
        ?Patient $patient,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): ?Notification {
        // A patient with no app account has nobody to notify. Not an error —
        // most hospital patients have never installed it.
        if (!$patient || empty($patient->user_id)) {
            return null;
        }

        try {
            return Notification::create([
                'user_id' => $patient->user_id,
                'patient_id' => $patient->id,
                'tenant_domain' => $tenant->domain,
                'audience' => Notification::PATIENT,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data ?: null,
                'role' => 'Patient',
                'is_read' => false,
            ]);
        } catch (\Throwable $th) {
            Log::warning('Could not write a patient notification.', [
                'tenant_id' => $tenant->id,
                'patient_id' => $patient->id,
                'type' => $type,
                'exception' => $th->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Tell a patient that a diagnostic result has been released.
     *
     * Called by the hospital-side laboratory and radiology services the moment a
     * result is marked Ready, which is the moment the app can show it. Takes the
     * order rather than ids so the wording carries the test name the patient
     * would recognise.
     *
     * The tenant is resolved from the header rather than from the patient app
     * context, because the request that releases a result is a member of staff's.
     *
     * @param  \App\Models\Laboratory|\App\Models\Radiology  $order
     */
    public function resultReleased($order, string $module = 'laboratory'): ?Notification
    {
        try {
            $tenant = Tenant::where('uuid', request()->header('X-Tenant-ID'))->first();

            if (!$tenant) {
                return null;
            }

            $patient = Patient::find($order->patient_id);
            $testName = $order->test_name ?: ($module === 'laboratory' ? 'test' : 'scan');

            return $this->notify(
                $tenant,
                $patient,
                $module === 'laboratory' ? 'lab_result_ready' : 'radiology_result_ready',
                $module === 'laboratory' ? 'Lab Result Ready' : 'Radiology Report Ready',
                'Your ' . $testName . ' result has been released and is now available.',
                [
                    'module' => $module,
                    'record_id' => $order->id,
                    'test_id' => $order->test_id,
                    'test_name' => $order->test_name,
                    'released_at' => now()->toDateTimeString(),
                ]
            );
        } catch (\Throwable $th) {
            // Releasing the result is what mattered; the notification is not
            // allowed to undo it.
            Log::warning('Could not announce a released result to the patient.', [
                'module' => $module,
                'record_id' => $order->id ?? null,
                'exception' => $th->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Mark one row read, without a second write when it already is.
     */
    protected function markAsRead(Notification $notification): Notification
    {
        if ($notification->is_unread) {
            $notification->forceFill([
                'is_read' => true,
                'read_at' => now(),
            ])->save();
        }

        return $notification;
    }

    /**
     * Split a list into the three headings the screen prints.
     *
     * Anything older than yesterday falls into Earlier rather than being dated
     * individually, which is what the design shows.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @return array<int, array<string, mixed>>
     */
    protected function group($records): array
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        $buckets = ['TODAY' => [], 'YESTERDAY' => [], 'EARLIER' => []];

        foreach ($records as $record) {
            $date = $record->created_at ? $record->created_at->copy()->startOfDay() : null;

            $key = match (true) {
                $date && $date->equalTo($today) => 'TODAY',
                $date && $date->equalTo($yesterday) => 'YESTERDAY',
                default => 'EARLIER',
            };

            $buckets[$key][] = $record;
        }

        // Empty headings are dropped rather than sent as empty arrays, so the
        // app renders what it is given without having to filter first.
        return collect($buckets)
            ->filter(fn($rows) => !empty($rows))
            ->map(fn($rows, $label) => ['label' => $label, 'count' => count($rows), 'records' => $rows])
            ->values()
            ->all();
    }
}
