<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\BaselinesMigrations;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Record migrations whose tables a tenant database already has.
 *
 * Tenant databases provisioned before migrations drove the schema (restored
 * from a dump, or created by hand) hold the tables without holding the rows in
 * their `migrations` table that say so. Every `migrate` run then replays those
 * create migrations and dies on the first "table already exists", which blocks
 * every new migration behind them.
 *
 * The reconciliation itself lives in BaselinesMigrations, which the landlord
 * command shares — the two differ only in which connection they point at and
 * which folder they read.
 *
 * @see \App\Console\Commands\LandlordBaselineMigrations for the landlord equivalent.
 */
class TenantBaselineMigrations extends Command
{
    use BaselinesMigrations;

    protected $signature = 'tenants:baseline-migrations
                            {--tenant= : Limit to one tenant, by id, uuid, database or name}
                            {--path=database/migrations/tenant : Migration folder to reconcile}
                            {--pretend : Show what would be recorded without writing anything}';

    protected $description = 'Mark tenant migrations whose tables already exist as run, so migrate can move past them';

    public function handle(): int
    {
        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->error('No matching tenant found.');

            return self::FAILURE;
        }

        $pretend = (bool) $this->option('pretend');
        $migrations = $this->migrationFiles($this->option('path'));

        if (empty($migrations)) {
            $this->error("No migrations found in {$this->option('path')}.");

            return self::FAILURE;
        }

        $originalDefault = config('database.default');

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info("Tenant: {$tenant->name} ({$tenant->database})");

            try {
                $this->useTenantConnection($tenant);
                $this->baselineConnection('tenant', $migrations, $pretend);
            } catch (\Throwable $th) {
                $this->error("  Failed: {$th->getMessage()}");
            }
        }

        $this->restoreConnection($originalDefault);

        $this->newLine();
        $this->info($pretend
            ? 'Nothing was written. Re-run without --pretend to apply.'
            : 'Baseline complete. Run the tenant migrations now.');

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    protected function tenants()
    {
        $needle = $this->option('tenant');

        if (empty($needle)) {
            return Tenant::all();
        }

        return Tenant::query()
            ->where('id', $needle)
            ->orWhere('uuid', $needle)
            ->orWhere('database', $needle)
            ->orWhere('name', $needle)
            ->get();
    }

    /**
     * Point the tenant connection at this tenant's database.
     *
     * Mirrors TenantMigrate rather than makeCurrent(): no tenant aware task
     * should fire while we are only reading and repairing bookkeeping.
     */
    protected function useTenantConnection(Tenant $tenant): void
    {
        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    protected function restoreConnection(string $originalDefault): void
    {
        config(['database.default' => $originalDefault]);
        DB::purge('tenant');
    }
}
