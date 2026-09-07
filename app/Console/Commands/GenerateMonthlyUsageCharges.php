<?php

namespace App\Console\Commands;

use App\Models\ClientUsageCharge;
use App\Models\Tenant;
use App\Models\UsageFee;
use App\Services\SuperAdmin\ClientManagement\VisitUsageService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

/**
 * Bill every hospital for the patient visits it recorded in a month.
 *
 * One charge row per hospital per month, keyed on (tenant_id, billing_month)
 * and written with updateOrCreate — so a month can be regenerated as often as
 * anyone likes and the hospital is never billed twice for it. That matters more
 * than it sounds: a rerun is the only way to correct a charge after visits are
 * backfilled, and it has to be safe to reach for.
 *
 * What counts as a chargeable visit is not decided here. It is decided once, in
 * VisitUsageService, because the superadmin screen shows the visit list behind
 * every charge this writes — and a hospital shown 40 visits on an invoice for
 * 38 has been given a reason not to pay it.
 *
 * @see \App\Services\SuperAdmin\ClientManagement\VisitUsageService
 */
class GenerateMonthlyUsageCharges extends Command
{
    /**
     * Usage:
     *   php artisan charges:generate-monthly                     bill last month, and catch up
     *   php artisan charges:generate-monthly --month=2026-08     treat August as the latest month
     *   php artisan charges:generate-monthly --tenant=101        one client only
     *   php artisan charges:generate-monthly --dry-run           show the workings, write nothing
     *   php artisan charges:generate-monthly --months=6          also RE-bill the last six months
     *   php artisan charges:generate-monthly --no-catch-up       target month only, no backfill
     *
     * @var string
     */
    protected $signature = 'charges:generate-monthly
                            {--month= : Latest month to bill, Y-m (defaults to the previous calendar month)}
                            {--months=1 : Re-bill this many months back from --month, even if already charged}
                            {--no-catch-up : Do not backfill earlier months that were never charged}
                            {--tenant= : Only bill this tenant id}
                            {--dry-run : Calculate and report without writing any charge}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly usage fee charges for active clients from their patient visits.';

    public function __construct(protected VisitUsageService $usage)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $latest = $this->latestMonth();

        if ($latest === null) {
            return self::FAILURE;
        }

        $tenants = $this->eligibleTenants();

        if ($tenants->isEmpty()) {
            $this->warn('No active clients with a usage fee were found. Nothing to bill.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info('========================================');
        $this->info('Billing up to ' . $latest->format('F Y')
            . ($this->option('no-catch-up') ? '' : ', catching up anything never charged'));
        $this->info('Clients: ' . $tenants->count() . ($dryRun ? '   [DRY RUN — nothing will be written]' : ''));
        $this->info('========================================');

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            $fee = $tenant->subscription?->usageFee;

            $this->line("\n<info>{$tenant->name}</info>  (id {$tenant->id}, db {$tenant->database})");

            // A subscription pointing at a usage fee that has since been deleted
            // bills nothing, and silently billing zero would look like a quiet
            // month rather than the broken configuration it is.
            if (!$fee) {
                $this->warn('  skipped: subscription #' . $tenant->subscription?->id . ' has no usage fee record');
                $skipped++;
                continue;
            }

            $this->warnAboutFee($fee);

            if (!$fee->billing_mode) {
                $skipped++;
                continue;
            }

            try {
                $months = $this->monthsFor($tenant, $latest);
            } catch (Throwable $e) {
                $this->error('  could not read this client: ' . $e->getMessage());
                $failed++;
                continue;
            }

            if ($months->isEmpty()) {
                $this->line('  nothing to bill — no visits recorded yet');
                $skipped++;
                continue;
            }

            foreach ($months as $month) {
                try {
                    $result = $this->billMonth($tenant, $fee, $month, $dryRun);

                    $result === 'skipped' ? $skipped++ : $generated++;
                } catch (Throwable $e) {
                    $this->error('  ' . $month->format('M Y') . ': ' . $e->getMessage());
                    $failed++;
                }
            }
        }

        $this->info("\n========================================");
        $this->info($dryRun ? 'Done (dry run — nothing written).' : 'Done.');
        $this->info("  Charged : {$generated}");
        $this->info("  Skipped : {$skipped}");
        $this->info("  Failed  : {$failed}");
        $this->info('========================================');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Work out and record one hospital's bill for one month.
     *
     * @return string  'charged' or 'skipped'
     */
    protected function billMonth(Tenant $tenant, UsageFee $fee, Carbon $month, bool $dryRun): string
    {
        $usage = $this->usage->monthlyUsage($tenant, $month, $fee);

        $label = $month->format('M Y');
        $existing = ClientUsageCharge::where('tenant_id', $tenant->id)
            ->whereDate('billing_month', $month->copy()->startOfMonth()->toDateString())
            ->first();

        if ($usage['chargeable_visits'] === 0) {
            // Nothing chargeable. A month that was never billed stays unbilled
            // rather than gaining a zero row nobody needs — but a month that WAS
            // billed and has since lost its visits is corrected down to zero,
            // because leaving the old amount standing would keep invoicing a
            // hospital for visits that are no longer there.
            if (!$existing) {
                $this->line("  {$label}: no chargeable visits — nothing billed");

                return 'skipped';
            }

            if ((float) $existing->total_amount === 0.0) {
                $this->line("  {$label}: no chargeable visits — already zero");

                return 'skipped';
            }

            $this->warn("  {$label}: no chargeable visits — correcting existing charge #{$existing->id} down to 0");
        }

        $this->line(sprintf(
            '  %s: %d visit(s) from %d patient(s), %d repeat → %d chargeable on the %s rule × %s = %s',
            $label,
            $usage['total_visits'],
            $usage['distinct_patients'],
            $usage['repeat_visits'],
            $usage['chargeable_visits'],
            $usage['billing_mode'],
            number_format($usage['fee_per_visit'], 2),
            number_format($usage['total_amount'], 2)
        ));

        if ($dryRun) {
            return 'charged';
        }

        ClientUsageCharge::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'billing_month' => $month->copy()->startOfMonth()->toDateString(),
            ],
            [
                'usage_fee_id' => $fee->id,
                'total_visits' => $usage['chargeable_visits'],
                'fee_per_visit' => $usage['fee_per_visit'],
                'total_amount' => $usage['total_amount'],

                // Only ever set on a charge being created. A month the finance
                // team has already marked Paid must not fall back to Pending
                // because somebody reran the generator.
                'status' => $existing->status ?? 'Pending',
            ]
        );

        return 'charged';
    }

    /**
     * The most recent month this run is allowed to bill.
     *
     * @return \Carbon\Carbon|null  null when --month could not be read
     */
    protected function latestMonth(): ?Carbon
    {
        $input = $this->option('month');

        if (!$input) {
            // The previous calendar month: the last one that is definitely over.
            // The current month is deliberately left alone — it is still
            // collecting visits, and billing it would invoice half a month.
            return now()->subMonth()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m', $input)->startOfMonth();
        } catch (Throwable) {
            $this->error('Invalid --month format. Use Y-m, e.g. --month=2026-08');

            return null;
        }
    }

    /**
     * Which months to bill this client for, oldest first.
     *
     * Two things go in the list.
     *
     * The catch-up: every month from this hospital's first recorded visit up to
     * the target that has no charge row at all. A client onboarded three months
     * ago, or one whose scheduled run was missed because the server was down,
     * is otherwise never billed for those months — nothing would ever look back
     * at them again. Months that already have a charge are left alone, so a
     * catch-up cannot disturb settled history.
     *
     * The forced window: the target month, plus however many months back
     * `--months` asks for. These are recomputed whether or not they were already
     * charged, which is how a month is corrected after visits are backfilled.
     *
     * @return \Illuminate\Support\Collection<int, \Carbon\Carbon>
     */
    protected function monthsFor(Tenant $tenant, Carbon $latest)
    {
        $months = collect();

        $force = max(1, (int) $this->option('months'));

        for ($back = $force - 1; $back >= 0; $back--) {
            $months->push($latest->copy()->subMonths($back)->startOfMonth());
        }

        if (!$this->option('no-catch-up')) {
            $months = $months->merge($this->unbilledMonths($tenant, $latest));
        }

        return $months
            ->filter(fn($month) => $month->lte($latest))
            ->unique(fn($month) => $month->format('Y-m'))
            ->sortBy(fn($month) => $month->format('Y-m'))
            ->values();
    }

    /**
     * Months this client has never had a charge written for.
     *
     * Bounded at the near end by the target month and at the far end by the
     * hospital's earliest visit — there is nothing to bill before a hospital
     * existed, and without that floor the walk has no start.
     *
     * @return \Illuminate\Support\Collection<int, \Carbon\Carbon>
     */
    protected function unbilledMonths(Tenant $tenant, Carbon $latest)
    {
        $first = $this->usage->firstVisitMonth($tenant);

        if (!$first || $first->gt($latest)) {
            return collect();
        }

        $billed = ClientUsageCharge::where('tenant_id', $tenant->id)
            ->pluck('billing_month')
            ->map(fn($month) => Carbon::parse($month)->format('Y-m'))
            ->flip();

        $months = collect();
        $cursor = $first->copy()->startOfMonth();

        while ($cursor->lte($latest)) {
            if (!$billed->has($cursor->format('Y-m'))) {
                $months->push($cursor->copy());
            }

            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Clients with an active subscription and a usage fee on it.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Tenant>
     */
    protected function eligibleTenants()
    {
        return Tenant::with('subscription.usageFee')
            ->whereHas('subscription', function ($query) {
                $query->where('status', 'Active')->whereNotNull('usage_fee_id');
            })
            ->when($this->option('tenant'), fn($query) => $query->where('id', (int) $this->option('tenant')))
            ->orderBy('id')
            ->get();
    }

    /**
     * Say so when a fee cannot be billed as configured.
     *
     * Neither of these can be fixed from here — they are data, set by whoever
     * created the plan — so the run reports them and carries on rather than
     * guessing at what was meant.
     */
    protected function warnAboutFee(UsageFee $fee): void
    {
        if (!$fee->billing_mode) {
            $this->warn("  skipped: usage fee #{$fee->id} ({$fee->name}) has neither the general nor the unique visit rule set");

            return;
        }

        if ($fee->has_conflicting_rules) {
            $this->warn("  note: usage fee #{$fee->id} ({$fee->name}) has BOTH visit rules set."
                . ' Billing on the general rule (every visit). Turn one off to make this unambiguous.');
        }
    }
}
