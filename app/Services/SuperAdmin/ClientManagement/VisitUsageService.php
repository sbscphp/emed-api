<?php

namespace App\Services\SuperAdmin\ClientManagement;

use App\Models\ClientUsageCharge;
use App\Models\Tenant;
use App\Models\UsageFee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class VisitUsageService
 *
 * What a hospital owes for the patient visits it recorded, and the visit by
 * visit working behind that number.
 *
 * Both the monthly billing command and the superadmin's Hospital visits screen
 * ask this class, and they have to: the command writes the charge and the
 * screen shows the visits it was built from, so a hospital's invoice would be
 * indefensible if the two counted differently. Everything about how a visit is
 * dated, classified and priced therefore lives here and nowhere else.
 *
 * Three things are worth knowing before reading further.
 *
 * A visit is dated by `created_at` — when it was recorded in eMed — rather than
 * by `arrival_date`. What is being billed is use of the system, the seeded rows
 * carry arrival dates months away from when they were written, and `created_at`
 * is the only one of the three date columns that is never null.
 *
 * A visit's TYPE is the rule its hospital is billed on, not something about the
 * visit itself. A hospital on the general-visit rule has general visits — all of
 * them, including a patient's second trip that month. A hospital on the
 * unique-visit rule has unique visits. A single hospital therefore shows one
 * type throughout, because it is on one plan.
 *
 * What varies visit to visit is whether the fee ATTACHES, and that is
 * `is_chargeable`. Under the general rule it always does. Under the unique rule
 * only a patient's first visit of the month does, and their later visits that
 * month are carried at zero — real visits, already paid for.
 *
 * Being a patient's first visit of the month is judged against the whole
 * calendar month, never against whatever window the screen is showing. A
 * patient's first visit in August is still their first even if the screen is
 * filtered to the last week of August and the earlier one is off-screen. So the
 * pass always runs over the whole months a window touches, and the window is
 * applied afterwards.
 *
 * @see \App\Console\Commands\GenerateMonthlyUsageCharges
 * @see \App\Services\SuperAdmin\ClientManagement\ClientManagementService::showClientVisitCharges()
 */
class VisitUsageService
{
    /**
     * Billed under the unique-visit rule: one per patient per month.
     */
    public const UNIQUE = 'Unique visit';

    /**
     * Billed under the general-visit rule: every visit, repeats included.
     */
    public const GENERAL = 'General visit';

    /**
     * Run something against one tenant's database and put the connection back.
     *
     * The billing command walks every tenant in one process, so a connection
     * left pointing at the last hospital is not a tidiness problem — it is the
     * next hospital being counted against the wrong database. Restoring in a
     * `finally` means that holds even when a tenant's database is missing and
     * the query throws.
     *
     * @template T
     *
     * @param  callable(\Illuminate\Database\Connection): T  $callback
     * @return T
     */
    public function onTenant(Tenant $tenant, callable $callback)
    {
        $previousDatabase = config('database.connections.tenant.database');
        $previousDefault = DB::getDefaultConnection();

        try {
            config(['database.connections.tenant.database' => $tenant->database]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            return $callback(DB::connection('tenant'));
        } finally {
            config(['database.connections.tenant.database' => $previousDatabase]);
            DB::purge('tenant');
            DB::setDefaultConnection($previousDefault);
        }
    }

    /**
     * Every visit in a window, each one classified and priced.
     *
     * Rows come back oldest first, as plain arrays rather than models: they are
     * read out of a tenant database no model is bound to, and they are only ever
     * displayed.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function visits(Tenant $tenant, Carbon $from, Carbon $to, ?UsageFee $fee = null): Collection
    {
        // Widened to whole months so "first visit this month" is judged against
        // the month, not against the edge of the window. See the class note.
        $classifyFrom = $from->copy()->startOfMonth();
        $classifyTo = $to->copy()->endOfMonth();

        $rows = $this->rawVisits($tenant, $classifyFrom, $classifyTo);
        $recorders = $this->recorderNames($tenant, $rows->pluck('initiated_by'));
        $statuses = $this->chargeStatuses($tenant, $classifyFrom, $classifyTo);

        $mode = $fee?->billing_mode;
        $feePerVisit = $fee ? $fee->amountForCycle('monthly') : 0.0;

        // One plan, one type, every row. A hospital billed for every visit does
        // not have "unique visits" hiding among them — it has general visits,
        // which is what its plan says it is charged for.
        //
        // A client with no usable rule reads as general and is charged nothing,
        // rather than leaving the column blank on a screen that has to render.
        $type = $mode === UsageFee::UNIQUE ? self::UNIQUE : self::GENERAL;

        $seen = [];

        return $rows
            ->map(function ($row) use (&$seen, $mode, $type, $feePerVisit, $recorders, $statuses) {
                $date = Carbon::parse($row->created_at);
                $month = $date->format('Y-m');

                // Rows arrive oldest first, so the first time a patient is seen
                // in a month is their earliest visit that month.
                $key = $month . ':' . $row->patient_id;
                $isFirstOfMonth = !isset($seen[$key]);
                $seen[$key] = true;

                // The whole difference between the two rules. General attaches
                // the fee to every visit; unique attaches it once per patient
                // per month and carries their later visits at zero.
                $isChargeable = $mode === UsageFee::GENERAL
                    || ($mode === UsageFee::UNIQUE && $isFirstOfMonth);

                $charge = $statuses[$month] ?? null;

                return [
                    'id' => (int) $row->id,
                    'visit_no' => $row->visitno,
                    'patient_id' => (int) $row->patient_id,

                    'date' => $date->toDateString(),
                    'date_label' => $date->format('M j, Y'),
                    'billing_month' => $month,
                    'arrival_date' => $row->arrival_date,

                    'visit_type' => $type,

                    // Whether this patient had already been seen that month.
                    // Informational under the general rule, where it changes
                    // nothing; under the unique rule it is exactly why a visit
                    // is carried at zero.
                    'is_first_of_month' => $isFirstOfMonth,
                    'is_repeat' => !$isFirstOfMonth,

                    // Zero on a repeat visit under a unique-visit plan: the row
                    // is real and belongs in the list, it simply costs nothing.
                    'is_chargeable' => $isChargeable,
                    'fee_applied' => $isChargeable ? $feePerVisit : 0.0,

                    // Visits are not paid one at a time — the month they fall in
                    // is. A month nobody has billed yet reads as Pending, which
                    // is what it is.
                    'payment_status' => $charge['status'] ?? 'Pending',
                    'charge_id' => $charge['id'] ?? null,

                    'recorded_by' => $recorders[(int) $row->initiated_by] ?? null,
                    'recorded_by_id' => $row->initiated_by ? (int) $row->initiated_by : null,
                ];
            })
            // Now, and only now, narrow to what was actually asked for.
            ->filter(fn($visit) => $visit['date'] >= $from->toDateString()
                && $visit['date'] <= $to->toDateString())
            ->values();
    }

    /**
     * What one calendar month comes to for one hospital.
     *
     * The command writes exactly this, and the screen's totals are the same
     * numbers summed a different way.
     *
     * @return array<string, mixed>
     */
    public function monthlyUsage(Tenant $tenant, Carbon $month, ?UsageFee $fee = null): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $visits = $this->visits($tenant, $from, $to, $fee);

        return $this->totals($visits, $fee) + [
            'billing_month' => $from->toDateString(),
            'fee_per_visit' => $fee ? $fee->amountForCycle('monthly') : 0.0,
            'billing_mode' => $fee?->billing_mode,
        ];
    }

    /**
     * Add a set of visits up.
     *
     * `chargeable_visits` is the number the invoice multiplies: every visit
     * under the general rule, one per patient per month under the unique rule.
     *
     * `unique_visits` and `general_visits` are that same number filed under the
     * rule it was billed on, so a hospital on the general plan reports all of
     * its visits as general and none as unique. They are counters for one plan
     * or the other, not a split of the visits into two kinds — one of them is
     * always zero.
     *
     * `distinct_patients` and `repeat_visits` describe the traffic rather than
     * the bill, and are reported whichever plan is in force.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $visits
     * @return array<string, mixed>
     */
    public function totals(Collection $visits, ?UsageFee $fee = null): array
    {
        $mode = $fee?->billing_mode;
        $chargeable = $visits->where('is_chargeable', true)->count();
        $firsts = $visits->where('is_first_of_month', true)->count();

        return [
            'total_visits' => $visits->count(),

            'unique_visits' => $mode === UsageFee::UNIQUE ? $chargeable : 0,
            'general_visits' => $mode === UsageFee::GENERAL ? $visits->count() : 0,

            'distinct_patients' => $firsts,
            'repeat_visits' => $visits->count() - $firsts,

            'chargeable_visits' => $chargeable,
            'total_amount' => round((float) $visits->sum('fee_applied'), 2),
        ];
    }

    /**
     * The month a hospital's earliest recorded visit falls in.
     *
     * Where a catch-up run starts from: there is nothing to bill before a
     * hospital's first visit, and walking back further would be walking back
     * forever. Null when the hospital has never recorded one.
     */
    public function firstVisitMonth(Tenant $tenant): ?Carbon
    {
        return $this->onTenant($tenant, function ($db) {
            if (!$db->getSchemaBuilder()->hasTable('patient_visits')) {
                return null;
            }

            $earliest = $db->table('patient_visits')->min('created_at');

            return $earliest ? Carbon::parse($earliest)->startOfMonth() : null;
        });
    }

    /**
     * How many visits this hospital has ever recorded, and how many this month.
     *
     * The two counters at the top of the screen that are not tied to whatever
     * period is being filtered on.
     *
     * @return array{total: int, this_month: int}
     */
    public function lifetimeCounts(Tenant $tenant): array
    {
        return $this->onTenant($tenant, function ($db) {
            if (!$db->getSchemaBuilder()->hasTable('patient_visits')) {
                return ['total' => 0, 'this_month' => 0];
            }

            return [
                'total' => (int) $db->table('patient_visits')->count(),
                'this_month' => (int) $db->table('patient_visits')
                    ->whereBetween('created_at', [
                        now()->startOfMonth()->toDateTimeString(),
                        now()->endOfMonth()->toDateTimeString(),
                    ])
                    ->count(),
            ];
        });
    }

    /**
     * The visit rows themselves, straight out of the tenant database.
     *
     * A tenant whose database has no patient_visits table answers empty rather
     * than throwing: that is a hospital nobody has finished setting up, not a
     * failure worth stopping a billing run over.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function rawVisits(Tenant $tenant, Carbon $from, Carbon $to): Collection
    {
        return $this->onTenant($tenant, function ($db) use ($from, $to) {
            if (!$db->getSchemaBuilder()->hasTable('patient_visits')) {
                return collect();
            }

            return collect($db->table('patient_visits')
                ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'patient_id', 'initiated_by', 'visitno', 'arrival_date', 'created_at']));
        });
    }

    /**
     * Names for the "Recorded by" column.
     *
     * Whoever started a visit may be a platform user or one of the hospital's
     * own staff — `initiated_by` does not say which, and the two live in
     * different databases. Both are looked up and the landlord wins, so the
     * column fills in either way instead of guessing and printing nothing.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $ids
     * @return array<int, string>
     */
    protected function recorderNames(Tenant $tenant, Collection $ids): array
    {
        $ids = $ids->filter()->map(fn($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $names = [];

        $tenantUsers = $this->onTenant($tenant, function ($db) use ($ids) {
            if (!$db->getSchemaBuilder()->hasTable('users')) {
                return collect();
            }

            return collect($db->table('users')
                ->whereIn('id', $ids)
                ->get(['id', 'fullname', 'first_name', 'last_name']));
        });

        foreach ($tenantUsers as $user) {
            $names[(int) $user->id] = $this->personName($user);
        }

        $landlordUsers = DB::connection('landlord')
            ->table('users')
            ->whereIn('id', $ids)
            ->get(['id', 'fullname', 'first_name', 'last_name']);

        foreach ($landlordUsers as $user) {
            $names[(int) $user->id] = $this->personName($user);
        }

        return $names;
    }

    /**
     * A person's name off a row that may carry it whole or in two halves.
     */
    protected function personName(object $user): string
    {
        $name = trim((string) ($user->fullname ?: ''));

        if ($name !== '') {
            return $name;
        }

        return trim(trim((string) $user->first_name) . ' ' . trim((string) $user->last_name)) ?: 'Unknown';
    }

    /**
     * The charge already written for each month in range, keyed Y-m.
     *
     * A visit shows the payment status of the month it falls in, so this is the
     * one thing the screen needs from the landlord side.
     *
     * @return array<string, array{id: int, status: string}>
     */
    protected function chargeStatuses(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        return ClientUsageCharge::query()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('billing_month', [$from->toDateString(), $to->toDateString()])
            ->get(['id', 'billing_month', 'status'])
            ->mapWithKeys(fn($charge) => [
                $charge->billing_month->format('Y-m') => [
                    'id' => (int) $charge->id,
                    'status' => (string) $charge->status,
                ],
            ])
            ->all();
    }
}
