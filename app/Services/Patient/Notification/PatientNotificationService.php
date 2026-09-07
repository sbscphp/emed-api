<?php

namespace App\Services\Patient\Notification;

use App\Exceptions\PatientAppException;
use App\Models\BillingLog;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientPayment;
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
            $page = $query->paginate($request['limit'] ?? 20);

            $this->decorate($page->getCollection());

            return [
                'unread_count' => $unread,
                'records' => $page,
            ];
        }

        $records = $query->limit($request['limit'] ?? 50)->get();

        $this->decorate($records);

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

        $notification = $this->markAsRead($notification);

        // Decorated after the read is written, never before: the hospital and
        // the invoice are set onto the model with setAttribute, which leaves it
        // dirty, and a save() after that would try to write them as columns.
        $this->decorate(collect([$notification]));

        return $notification;
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
     * Attach what the rows cannot answer for themselves.
     *
     * Takes the whole list rather than one row at a time, and is the only way
     * rows are decorated — the detail screen passes a collection of one. The
     * invoices and payments the rows point at are read in one query each, so a
     * page of fifty notifications costs the same two queries as a single one
     * rather than a hundred.
     *
     * The rows are mutated in place, which is what lets a paginator's own
     * collection be handed straight in.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @return \Illuminate\Support\Collection
     */
    protected function decorate($records)
    {
        $tenant = $this->context->tenant();

        // The hospital goes onto every row. A patient's account is shared by
        // the hospitals that registered it, and the app lists one hospital at a
        // time, but the row itself says only what happened — not where — so the
        // screen would have nothing to print under the title.
        //
        // Read from the request context rather than from `tenant_domain`,
        // because these rows live in that hospital's own database: the row being
        // readable at all is what says which hospital it belongs to.
        $hospital = [
            'uuid' => $tenant->uuid,
            'name' => $tenant->name,
            'logo' => $tenant->logo,
        ];

        $bills = $this->billsFor($records);
        $payments = $this->paymentsFor($records);

        foreach ($records as $record) {
            $record->setAttribute('hospital', $hospital);
            $record->setAttribute('billing', $this->billingFor($record, $bills, $payments));
        }

        return $records;
    }

    /**
     * The invoice a billing notification is about.
     *
     * "Payment successful" is worth reading for the invoice number, what the
     * bill now stands paid at and how it was paid — none of which the row
     * itself stores. They are read from the bill instead, live, so a
     * notification from last month reflects what the invoice says today rather
     * than what it said when the row was written.
     *
     * Keyed off `billing_id` in the row's own data rather than off a list of
     * types, so any notification that names a bill gets the block and a new kind
     * of billing notification needs no change here.
     *
     * @param  \Illuminate\Support\Collection  $bills
     * @param  \Illuminate\Support\Collection  $payments
     * @return array<string, mixed>|null
     */
    protected function billingFor(Notification $notification, $bills, $payments): ?array
    {
        $data = $notification->data ?: [];
        $bill = $bills->get($data['billing_id'] ?? null);

        if (!$bill) {
            return null;
        }

        return [
            'billing_id' => $bill->id,
            'invoice_number' => $bill->invoice_number,
            'amount_paid' => round((float) $bill->amount_paid, 2),
            'payment_method' => $this->paymentMethod($bill, $data, $payments),
        ];
    }

    /**
     * Every bill the list points at, in one query, keyed by id.
     *
     * Scoped to the signed in patient, so a row naming a bill that is not
     * theirs simply finds nothing. A lookup that fails answers with an empty
     * set rather than throwing: a notification screen must not go down over the
     * invoice behind one of its rows.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @return \Illuminate\Support\Collection
     */
    protected function billsFor($records)
    {
        $ids = $this->idsFrom($records, 'billing_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        try {
            return BillingLog::query()
                ->where('patient_id', $this->context->patient()->id)
                ->whereIn('id', $ids->all())
                ->get()
                ->keyBy('id');
        } catch (\Throwable $th) {
            Log::warning('Could not attach billing detail to patient notifications.', [
                'billing_ids' => $ids->all(),
                'exception' => $th->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Every payment the list points at, in one query, keyed by id.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @return \Illuminate\Support\Collection
     */
    protected function paymentsFor($records)
    {
        $ids = $this->idsFrom($records, 'payment_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        try {
            return PatientPayment::query()
                ->whereIn('id', $ids->all())
                ->get()
                ->keyBy('id');
        } catch (\Throwable $th) {
            Log::warning('Could not read the payments behind patient notifications.', [
                'payment_ids' => $ids->all(),
                'exception' => $th->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * The ids one key of the rows' `data` points at, deduplicated.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @return \Illuminate\Support\Collection
     */
    protected function idsFrom($records, string $key)
    {
        return collect($records)
            ->map(fn($record) => ($record->data ?: [])[$key] ?? null)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * How the bill was paid.
     *
     * A notification about one payment answers with that payment's channel,
     * which is what the patient actually used — card, bank transfer. The bill's
     * own column is the fallback, for a row that names a bill without naming a
     * payment.
     *
     * @param  array<string, mixed>  $data
     * @param  \Illuminate\Support\Collection  $payments
     */
    protected function paymentMethod(BillingLog $bill, array $data, $payments): ?string
    {
        $payment = $payments->get($data['payment_id'] ?? null);

        if ($payment && !empty($payment->channel)) {
            return $payment->channel;
        }

        return $bill->payment_method;
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
