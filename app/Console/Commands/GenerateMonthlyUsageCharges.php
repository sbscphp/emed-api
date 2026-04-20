<?php

namespace App\Console\Commands;

use App\Models\ClientUsageCharge;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateMonthlyUsageCharges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *   php artisan charges:generate-monthly              (bills previous calendar month)
     *   php artisan charges:generate-monthly --month=2026-03  (bills a specific month)
     *
     * @var string
     */
    protected $signature = 'charges:generate-monthly
                            {--month= : Target month in Y-m format (defaults to previous calendar month)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly usage fee charge records for all active clients based on patient visits.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // ── Resolve target billing month ──────────────────────────────────────
        $monthInput = $this->option('month');

        if ($monthInput) {
            try {
                $billingDate = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
            } catch (Throwable) {
                $this->error("Invalid --month format. Use Y-m, e.g. --month=2026-03");
                return self::FAILURE;
            }
        } else {
            // Default: previous calendar month (dynamic — handles 28/29/30/31 days correctly)
            $billingDate = Carbon::now()->subMonth()->startOfMonth();
        }

        $startDate = $billingDate->copy()->startOfMonth()->toDateTimeString();
        $endDate   = $billingDate->copy()->endOfMonth()->toDateTimeString();

        $this->info("========================================");
        $this->info("Generating charges for: {$billingDate->format('F Y')}");
        $this->info("Period: {$startDate} → {$endDate}");
        $this->info("========================================");

        // ── Fetch tenants that have an Active subscription with a usage fee ──
        $tenants = Tenant::with('subscription.usageFee')
            ->whereHas('subscription', function ($query) {
                $query->where('status', 'Active')
                      ->whereNotNull('usage_fee_id');
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn("No active tenants with subscriptions found. Nothing to process.");
            return self::SUCCESS;
        }

        $this->info("Found {$tenants->count()} eligible tenant(s). Processing...\n");

        $generated = 0;
        $skipped   = 0;
        $failed    = 0;

        foreach ($tenants as $tenant) {
            $subscription = $tenant->subscription;
            $usageFee     = $subscription->usageFee;

            $this->line("Processing: <info>{$tenant->name}</info> (DB: {$tenant->database})");

            // Defensive: skip if usage fee record was soft-deleted or missing
            if (!$usageFee) {
                $this->warn("  → Skipped: usage fee not found for subscription #{$subscription->id}");
                $skipped++;
                continue;
            }

            try {
                // ── Switch to tenant database ─────────────────────────────────
                config(['database.connections.tenant.database' => $tenant->database]);
                DB::purge('tenant');
                DB::reconnect('tenant');

                // ── Calculate visit count based on fee type ───────────────────
                $totalVisits = 0;

                if ($usageFee->is_general_visit) {
                    // Count ALL visit rows within the billing month
                    $totalVisits = DB::connection('tenant')
                        ->table('patient_visits')
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->count();

                } elseif ($usageFee->is_unique_visit) {
                    // Count DISTINCT patients who visited within the billing month
                    $totalVisits = DB::connection('tenant')
                        ->table('patient_visits')
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->distinct('patient_id')
                        ->count('patient_id');
                }

                // ── Skip tenants with zero visits ─────────────────────────────
                if ($totalVisits === 0) {
                    $this->line("  → Skipped: zero visits in {$billingDate->format('F Y')}");
                    $skipped++;
                    continue;
                }

                $feePerVisit = (float) $usageFee->amount;
                $totalAmount = $totalVisits * $feePerVisit;

                // ── Restore landlord connection before writing charge record ──
                $this->restoreLandlordConnection();

                // ── Save charge (idempotent: updateOrCreate) ──────────────────
                ClientUsageCharge::updateOrCreate(
                    [
                        'tenant_id'     => $tenant->id,
                        'billing_month' => $billingDate->toDateString(),
                    ],
                    [
                        'usage_fee_id'  => $usageFee->id,
                        'total_visits'  => $totalVisits,
                        'fee_per_visit' => $feePerVisit,
                        'total_amount'  => $totalAmount,
                        'status'        => 'Pending',
                    ]
                );

                $this->info("  → Charged: {$totalVisits} visit(s) × ₦{$feePerVisit} = ₦{$totalAmount}");
                $generated++;

            } catch (Throwable $e) {
                $this->error("  → Error: " . $e->getMessage());
                $failed++;

            } finally {
                // Always restore landlord connection
                $this->restoreLandlordConnection();
            }

            $this->line("----------------------------------------");
        }

        // ── Summary ───────────────────────────────────────────────────────────
        $this->info("\n========================================");
        $this->info("Done.");
        $this->info("  Generated : {$generated}");
        $this->info("  Skipped   : {$skipped}");
        $this->info("  Failed    : {$failed}");
        $this->info("========================================");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Restore the landlord database as the active connection.
     */
    private function restoreLandlordConnection(): void
    {
        config(['database.default' => 'landlord']);
        DB::purge('landlord');
        DB::reconnect('landlord');
        DB::setDefaultConnection('landlord');
    }
}
