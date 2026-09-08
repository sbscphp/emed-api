<?php

namespace App\Services\SuperAdmin\ClientManagement;

use App\Enums\GeneralEnums;
use App\Exports\ClientUsageChargeExport;
use App\Models\ClientUsageCharge;
use App\Helpers\GeneralHelper;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UsageFee;
use App\Services\Revamp\AuthenticationService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ClientManagementService
{
    protected AuthenticationService $authenticationService;

    protected VisitUsageService $visitUsage;

    public function __construct(AuthenticationService $authenticationService, VisitUsageService $visitUsage)
    {
        $this->authenticationService = $authenticationService;
        $this->visitUsage = $visitUsage;
    }

    public function overview($request)
    {
        $query = Tenant::query()
            ->with('subscription.usageFee')
            ->when($request->search_param, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('email', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('registration_number', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('state_city', 'LIKE', '%' . $request->search_param . '%');
                });
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->usage_fee_id, function ($query) use ($request) {
                $query->whereHas('subscription', function ($sub) use ($request) {
                    $sub->where('usage_fee_id', $request->usage_fee_id);
                });
            })
            ->when(($request->sort_by ?? null) === 'name_ascending', function ($query) {
                $query->orderBy('name', 'ASC');
            })
            ->when(($request->sort_by ?? null) === 'name_descending', function ($query) {
                $query->orderBy('name', 'DESC');
            });

        $paginate = $request->paginate ?? true;

        if ($paginate) {
            return $query->orderBy('id', 'DESC')->paginate($request->limit ?? 15);
        }

        return [
            'records' => $query->orderBy('id', 'DESC')->get(),
        ];
    }

    public function createClient($request)
    {
        $data = [
            'hospital_name' => $request->input('hospital_name'),
            'country' => $request->input('country'),
            'state_city' => $request->input('state_city'),
            'registration_number' => $request->input('registration_number'),
            'hospital_email' => $request->input('hospital_email'),
            'hospital_phoneno' => $request->input('hospital_phoneno'),
            'hospital_address' => $request->input('hospital_address'),
            'hospital_type' => $request->input('hospital_type'),
            'admin_firstname' => $request->input('admin_firstname'),
            'admin_lastname' => $request->input('admin_lastname'),
            'admin_email' => $request->input('admin_email'),
            'admin_phoneno' => $request->input('admin_phoneno'),
            'admin_password' => $request->input('admin_password'),
            'skip_email_verification' => true,
        ];

        $result = $this->authenticationService->create($data);
        /** @var \App\Models\Tenant $tenant */
        $tenant = $result['tenant'];

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'usage_fee_id' => $request->input('usage_fee_id'),
            'license_fee' => $request->input('license_fee'),
            'license_start_date' => $request->input('license_start_date'),
            'license_end_date' => $request->input('license_end_date'),
            'status' => GeneralEnums::ACTIVE->value,
        ]);

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Tenant',
            'action_module' => 'Super Admin Clients',
            'action_id' => $tenant->id,
            'action' => 'Create',
            'log_name' => 'Create Client',
            'description' => sprintf('Created client hospital %s.', $tenant->name),
            'module_accessed' => 'Super Admin Client Management',
        ]);

        $tenant->load('subscription.usageFee');

        return [
            'tenant' => $tenant,
            'user' => $result['user'],
            'subscription' => $subscription,
        ];
    }

    public function showClient($id)
    {
        return Tenant::with('subscription.usageFee')->find($id);
    }

    public function updateClient($id, $request)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            throw new \Exception('Client not found.');
        }

        $oldData = $tenant->only(['name', 'country', 'state_city', 'registration_number', 'email', 'phone_number', 'address', 'hospital_type', 'status']);

        $tenant->update([
            'name' => $request->input('hospital_name'),
            'country' => $request->input('country'),
            'state_city' => $request->input('state_city'),
            'registration_number' => $request->input('registration_number'),
            'email' => $request->input('hospital_email'),
            'phone_number' => $request->input('hospital_phoneno'),
            'address' => $request->input('hospital_address'),
            'hospital_type' => $request->input('hospital_type'),
            'status' => $request->input('status', $tenant->status ?? GeneralEnums::ACTIVE->value),
        ]);

        $subscription = $tenant->subscription;

        if ($subscription) {
            $subscription->update([
                'usage_fee_id' => $request->input('usage_fee_id'),
                'license_fee' => $request->input('license_fee'),
                'license_start_date' => $request->input('license_start_date'),
                'license_end_date' => $request->input('license_end_date'),
            ]);
        } else {
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'usage_fee_id' => $request->input('usage_fee_id'),
                'license_fee' => $request->input('license_fee'),
                'license_start_date' => $request->input('license_start_date'),
                'license_end_date' => $request->input('license_end_date'),
                'status' => GeneralEnums::ACTIVE->value,
            ]);
        }

        $newData = $tenant->fresh()->only(['name', 'country', 'state_city', 'registration_number', 'email', 'phone_number', 'address', 'hospital_type', 'status']);

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Tenant',
            'action_module' => 'Super Admin Clients',
            'action_id' => $tenant->id,
            'action' => 'Update',
            'log_name' => 'Update Client',
            'description' => sprintf('Updated client hospital %s.', $tenant->name),
            'module_accessed' => 'Super Admin Client Management',
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);

        return $tenant->fresh()->load('subscription.usageFee');
    }

    /**
     * The Hospital visits tab: what this client has been billed, month by month.
     *
     * One row per monthly charge, which is what a hospital is actually invoiced
     * and what the finance team marks Paid. The visits behind a month are not
     * listed here — there can be thousands of them, and none of them is
     * separately payable.
     *
     * The month counts come off the charge rows, so the screen shows what was
     * billed rather than what a recount would say today. The `current_month`
     * block is the exception and is deliberately live: it is the month still in
     * progress, which by definition has no charge row yet, and a hospital
     * wanting to know what it is running up cannot wait for the month to end to
     * find out.
     *
     * Filters: `period` (this_month, last_month, this_year, last_year, all) or
     * an explicit `from`/`to`, or `month=Y-m`, or `date=Y-m-d` for the month
     * that day falls in; `status` to narrow to Paid or Pending; `search_param`
     * against the month name or whoever last updated it; `limit`, `page`, and
     * `export=1` for the spreadsheet.
     *
     * @return array<string, mixed>|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function showClientVisitCharges($id, $request)
    {
        $tenant = Tenant::with('subscription.usageFee')->find($id);

        if (!$tenant) {
            throw new \Exception('Client not found.');
        }

        $fee = $tenant->subscription?->usageFee;
        [$from, $to, $period] = $this->chargePeriod($request);

        $charges = $this->chargeQuery($tenant, $request, $from, $to)->get();

        if (filter_var($request->export, FILTER_VALIDATE_BOOLEAN)) {
            return Excel::download(
                new ClientUsageChargeExport($charges),
                'usage-charges-' . Str::slug($tenant->name) . '-' . now()->format('Y-m-d') . '.xlsx'
            );
        }

        return [
            'client' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'usage_fee' => $fee ? [
                    'id' => $fee->id,
                    'name' => $fee->name,
                    'amount' => $fee->amountForCycle('monthly'),
                    'billing_mode' => $fee->billing_mode,

                    // Surfaced rather than hidden: a fee with both rules set is
                    // billed on the general one, and the person looking at the
                    // invoice should be able to see that is why.
                    'has_conflicting_rules' => $fee->has_conflicting_rules,
                ] : null,
            ],
            'summary' => $this->chargeSummary($tenant, $charges, $fee),
            'filter' => [
                'period' => $period,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'status' => $request->status,
                'date' => $request->date,
                'month' => $request->month,
            ],
            'records' => $this->paginateRows(
                $charges->map(fn($charge) => $this->chargeRow($charge, $fee)),
                $request
            ),
        ];
    }

    /**
     * One month of billing, as the table reads it.
     *
     * `total_visits` is the count that was actually charged for, which under a
     * unique-visit plan is fewer than the month's visits — one per patient, not
     * one per trip. It is the number multiplied by the fee, and it is stored on
     * the charge, so it stays right even after the plan is changed.
     *
     * @return array<string, mixed>
     */
    protected function chargeRow(ClientUsageCharge $charge, $fee): array
    {
        $month = $charge->billing_month;
        $plan = $charge->usageFee ?: $fee;

        return [
            'id' => $charge->id,

            'billing_month' => $month->format('Y-m'),
            'date' => $month->toDateString(),
            'date_label' => $month->format('F Y'),

            // The rule the hospital is billed on. One plan means one type, so
            // every row on a client reads the same — it labels the charge, it
            // does not sort the visits into two kinds.
            'visit_type' => $plan?->billing_mode === UsageFee::UNIQUE
                ? VisitUsageService::UNIQUE
                : VisitUsageService::GENERAL,
            'billing_mode' => $plan?->billing_mode,

            'usage_fee' => $plan ? ['id' => $plan->id, 'name' => $plan->name] : null,

            'total_visits' => (int) $charge->total_visits,
            'fee_per_visit' => round((float) $charge->fee_per_visit, 2),
            'total_amount' => round((float) $charge->total_amount, 2),

            'payment_status' => $charge->status,
            'is_paid' => $charge->status === 'Paid',

            'recorded_by' => $charge->updatedBy ? $this->personName($charge->updatedBy) : null,
            'generated_at' => optional($charge->created_at)->toDateTimeString(),
            'updated_at' => optional($charge->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * The charges this screen is showing, newest month first.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function chargeQuery(Tenant $tenant, $request, Carbon $from, Carbon $to)
    {
        return ClientUsageCharge::with(['usageFee', 'updatedBy'])
            ->where('tenant_id', $tenant->id)
            ->whereBetween('billing_month', [$from->toDateString(), $to->toDateString()])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search_param, function ($q) use ($request) {
                $search = '%' . $request->search_param . '%';

                $q->where(function ($q) use ($search, $request) {
                    // The month as it is written on the row — "August 2026",
                    // "Aug", "2026-08" all reach it, because that is what
                    // somebody looking at this table would type.
                    $q->whereRaw("DATE_FORMAT(billing_month, '%M %Y') LIKE ?", [$search])
                        ->orWhereRaw("DATE_FORMAT(billing_month, '%Y-%m') LIKE ?", [$search])
                        ->orWhereHas('updatedBy', function ($u) use ($search) {
                            $u->where('first_name', 'LIKE', $search)
                                ->orWhere('last_name', 'LIKE', $search)
                                ->orWhere('fullname', 'LIKE', $search)
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$search]);
                        });
                });
            })
            ->orderBy('billing_month', 'DESC');
    }

    /**
     * The card above the table.
     *
     * The money is read off the charge rows rather than recounted from the
     * visits, so it says what this client was billed — which is the number they
     * will argue about — rather than what a recount would make it today.
     *
     * `current_month` is the one live figure, and the reason it is here: the
     * month in progress has no charge row yet, and a hospital should be able to
     * see what it is running up before the bill lands.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\ClientUsageCharge>  $charges
     * @return array<string, mixed>
     */
    protected function chargeSummary(Tenant $tenant, $charges, $fee): array
    {
        $lifetime = $this->visitUsage->lifetimeCounts($tenant);

        $billed = (int) $charges->sum('total_visits');
        $total = round((float) $charges->sum('total_amount'), 2);
        $paid = round((float) $charges->where('status', 'Paid')->sum('total_amount'), 2);

        $mode = $fee?->billing_mode;

        return [
            'currency' => 'NGN',

            'total_visits' => $lifetime['total'],
            'visits_this_month' => $lifetime['this_month'],

            // Counters for the rule this client is on. A hospital billed for
            // every visit reports all of them as general and none as unique —
            // the other counter is zero, not hiding a second kind of visit.
            'unique_visits' => $mode === UsageFee::UNIQUE ? $billed : 0,
            'general_visits' => $mode === UsageFee::GENERAL ? $billed : 0,
            'billed_visits' => $billed,

            'months_billed' => $charges->count(),
            'fee_per_visit' => $fee ? $fee->amountForCycle('monthly') : 0.0,

            'total_fees' => $total,
            'paid_fees' => $paid,
            'outstanding_fees' => round($total - $paid, 2),

            'current_month' => $this->currentMonthAccrual($tenant, $fee),
        ];
    }

    /**
     * What the month in progress has run up so far.
     *
     * Counted live from the tenant's visits, because there is nothing else to
     * count: the billing command deliberately leaves the current month alone
     * until it is over, so no charge row exists yet. This is an estimate of what
     * will be billed, and it moves for the rest of the month.
     *
     * `is_billed` says whether a charge has since been written for it, which
     * happens when someone bills the current month by hand — the accrual then
     * has an invoice behind it rather than being a forecast.
     *
     * @return array<string, mixed>
     */
    protected function currentMonthAccrual(Tenant $tenant, $fee): array
    {
        $month = now()->startOfMonth();
        $usage = $this->visitUsage->monthlyUsage($tenant, $month, $fee);

        $charge = ClientUsageCharge::where('tenant_id', $tenant->id)
            ->whereDate('billing_month', $month->toDateString())
            ->first();

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),

            'total_visits' => $usage['total_visits'],
            'distinct_patients' => $usage['distinct_patients'],
            'repeat_visits' => $usage['repeat_visits'],

            // What the fee will actually be applied to: every visit under the
            // general rule, one per patient under the unique one.
            'chargeable_visits' => $usage['chargeable_visits'],
            'fee_per_visit' => $usage['fee_per_visit'],

            // The running total. Not owed yet — the month is not over.
            'accrued_amount' => $usage['total_amount'],

            'is_billed' => (bool) $charge,
            'charge_id' => $charge?->id,
            'payment_status' => $charge?->status,
        ];
    }

    /**
     * The window of billing months the screen is showing.
     *
     * `all` by default. The screen opens on a client whose billing may be
     * months old, and defaulting to the current month would answer an empty
     * list — the current month is not billed until it ends — which reads as a
     * broken endpoint rather than as a filter.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function chargePeriod($request): array
    {
        // A charge covers a month, so a single date means the month it lands in
        // rather than that one day.
        if ($request->date) {
            try {
                $day = Carbon::parse($request->date);

                return [$day->copy()->startOfMonth(), $day->copy()->endOfMonth(), 'month'];
            } catch (\Throwable) {
                // Unreadable date — fall through to the period rules below.
            }
        }

        if ($request->month) {
            try {
                $month = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();

                return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth(), 'month'];
            } catch (\Throwable) {
                // Unreadable month — fall through.
            }
        }

        if ($request->start_date || $request->end_date) {
            $from = $request->start_date ? Carbon::parse($request->start_date)->startOfMonth() : Carbon::createFromTimestamp(0);
            $to = $request->end_date ? Carbon::parse($request->end_date)->endOfMonth() : now()->endOfMonth();

            return [$from, $to, 'custom'];
        }

        return match ($request->period) {
            'this_month' => [now()->startOfMonth(), now()->endOfMonth(), 'this_month'],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth(), 'last_month'],
            'this_year' => [now()->startOfYear(), now()->endOfYear(), 'this_year'],
            'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear(), 'last_year'],
            default => [Carbon::createFromTimestamp(0), now()->endOfMonth(), 'all'],
        };
    }

    /**
     * A person's name off a row that may carry it whole or in two halves.
     */
    protected function personName($user): string
    {
        $name = trim((string) ($user->fullname ?: ''));

        if ($name !== '') {
            return $name;
        }

        return trim(trim((string) $user->first_name) . ' ' . trim((string) $user->last_name)) ?: 'Unknown';
    }

    /**
     * Hand rows back as a page, with the totals and links around them.
     *
     * Built from the collection rather than by the query builder because the
     * rows have already been mapped into the shape the screen reads.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $records
     */
    protected function paginateRows($records, $request): LengthAwarePaginator
    {
        $perPage = max(1, (int) ($request->limit ?? 15));
        $page = max(1, (int) LengthAwarePaginator::resolveCurrentPage());

        return new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => Arr::except($request->query(), ['page']),
            ]
        );
    }

    /**
     * Mark a month settled, or put it back to Pending.
     *
     * Note the `{id}` here is a CHARGE id, not a client id as it is on the
     * route above it. Every row the visits list returns carries the `charge_id`
     * of the month it falls in for exactly this call — a month with no charge
     * row yet has none, and cannot be marked paid until the billing command has
     * run for it.
     */
    public function updateClientVisitCharges($id, $request)
    {
        $charge = ClientUsageCharge::where('id', $id)->first();

        if (!$charge) {
            throw new \Exception('Usage charge not found.');
        }

        $currentUser = Auth::guard('api')->user();

        // if (!$currentUser) {
        //     throw new \Exception('Authenticated user not found.');
        // }

        $oldStatus = $charge->status;
        $charge->status = $request->input('status');
        $charge->updated_by = $currentUser?->id;
        $charge->save();

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\ClientUsageCharge',
            'action_module' => 'Super Admin Clients',
            'action_id' => $charge->id,
            'action' => 'Update',
            'log_name' => 'Update Client Usage Charge',
            'description' => sprintf('Updated usage charge status from %s to %s for hospital %s.', $oldStatus, $charge->status, $charge->tenant?->name ?? 'Unknown'),
            'module_accessed' => 'Super Admin Client Management',
            'old_data' => ['status' => $oldStatus],
            'new_data' => ['status' => $charge->status],
        ]);

        return $charge->load('usageFee', 'updatedBy:id,first_name,last_name');
    }

    public function toggleClientStatus($id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            throw new \Exception('Client not found.');
        }

        $tenant->status = $tenant->status === GeneralEnums::ACTIVE->value
            ? GeneralEnums::INACTIVE->value
            : GeneralEnums::ACTIVE->value;
        $oldStatus = $tenant->status;
        $tenant->save();

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Tenant',
            'action_module' => 'Super Admin Clients',
            'action_id' => $tenant->id,
            'action' => 'Toggle Status',
            'log_name' => 'Toggle Client Status',
            'description' => sprintf('Changed client %s status from %s to %s.', $tenant->name, $oldStatus, $tenant->status),
            'module_accessed' => 'Super Admin Client Management',
            'old_data' => ['status' => $oldStatus],
            'new_data' => ['status' => $tenant->status],
        ]);

        return $tenant;
    }

    public function removeClient($id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            throw new \Exception('Client not found.');
        }

        GeneralHelper::storeLandlordAuditLog([
            'action_type' => 'Models\\Tenant',
            'action_module' => 'Super Admin Clients',
            'action_id' => $tenant->id,
            'action' => 'Delete',
            'log_name' => 'Remove Client',
            'description' => sprintf('Removed client hospital %s.', $tenant->name),
            'module_accessed' => 'Super Admin Client Management',
        ]);

        $tenant->delete();
    }
}
