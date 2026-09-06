<?php

namespace App\Console\Commands;

use App\Enums\PatientVisitStatusEnums;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloseStaleVisits extends Command
{
    protected $signature = 'visits:close-stale';

    protected $description = 'Auto-close outpatient visits left open past their day (admitted/inpatient visits are exempt).';

    public function handle()
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return;
        }

        $today = now()->toDateString();

        foreach ($tenants as $tenant) {
            try {
                // Point the tenant connection at this tenant's DB (mirrors TenantMigrate /
                // GenerateMonthlyUsageCharges; avoids makeCurrent() which varies by env).
                config(['database.connections.tenant.database' => $tenant->database]);
                DB::purge('tenant');
                DB::reconnect('tenant');

                $closed = DB::connection('tenant')->table('patient_visits')
                    ->where('status', '!=', PatientVisitStatusEnums::COMPLETED->value)
                    ->whereDate('arrival_date', '<', $today)
                    ->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('admitted_patients')
                            ->whereColumn('admitted_patients.visit_id', 'patient_visits.id')
                            ->where('admitted_patients.status', '!=', 'Discharged')
                            ->whereNull('admitted_patients.deleted_at');
                    })
                    ->update([
                        'status'         => PatientVisitStatusEnums::COMPLETED->value,
                        'departure_date' => now(),
                        'updated_at'     => now(),
                    ]);

                $this->info("Tenant {$tenant->name}: closed {$closed} stale visit(s).");
            } catch (\Throwable $e) {
                $this->error("Tenant {$tenant->name}: " . $e->getMessage());
            } finally {
                // Always restore the landlord connection.
                config(['database.default' => 'landlord']);
                DB::purge('landlord');
                DB::reconnect('landlord');
                DB::setDefaultConnection('landlord');
            }
        }

        $this->info('Done closing stale outpatient visits.');
    }
}
