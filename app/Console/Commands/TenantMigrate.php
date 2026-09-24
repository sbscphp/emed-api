<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Run the tenant migrations against every tenant database.
 *
 * Tenant migrations cannot be run with a plain `migrate --path=database/migrations/tenant`:
 * that targets whatever the default connection is — the landlord database — and
 * so tries to build the tenant schema inside the central database, where it dies
 * on the first table the landlord already owns ("Base table or view already
 * exists: users"). Each tenant keeps its own database, so the folder has to be
 * replayed once per tenant with the `tenant` connection pointed at it.
 *
 * @see \App\Listeners\BlockTenantMigrationsOnLandlord for the guard that turns
 *      that mistake into a message instead of a half-written landlord schema.
 * @see \App\Console\Commands\TenantBaselineMigrations for tenant databases whose
 *      tables exist but were never recorded in their `migrations` table.
 */
class TenantMigrate extends Command
{
    use ConfirmableTrait;

    protected $signature = 'tenant:migrate
                            {--tenant= : Limit to one tenant, by id, uuid, database or name}
                            {--path=database/migrations/tenant : Migration folder to run}
                            {--pretend : Dump the SQL instead of running it}
                            {--step : Record each migration in its own batch, so it can be rolled back on its own}
                            {--force : Run without the production confirmation}';

    protected $description = 'Run the tenant migrations against each tenant database';

    public function handle(): int
    {
        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->error('No matching tenant found.');

            return self::FAILURE;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $originalDefault = DB::getDefaultConnection();
        $landlordDatabase = config('database.connections.' . config('multitenancy.landlord_database_connection_name') . '.database');

        $failed = [];

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->components->info("Tenant: {$tenant->name} ({$tenant->database})");

            try {
                // A tenant with no database of its own, or one pointed at the
                // landlord, would write the tenant schema straight into the
                // central database — the very failure this command exists to
                // avoid — so it is skipped rather than migrated.
                if (blank($tenant->database)) {
                    throw new \RuntimeException('Tenant has no database name recorded.');
                }

                if ($tenant->database === $landlordDatabase) {
                    throw new \RuntimeException("Tenant database matches the landlord database ({$landlordDatabase}).");
                }

                $this->useTenantConnection($tenant);

                $this->line('  connection: tenant → ' . DB::connection('tenant')->getDatabaseName());

                $exitCode = $this->call('migrate', array_filter([
                    // Explicit, so the run does not depend on which connection
                    // happens to be default when this command is invoked.
                    '--database' => 'tenant',
                    '--path' => $this->option('path'),
                    '--force' => true,
                    '--pretend' => (bool) $this->option('pretend'),
                    '--step' => (bool) $this->option('step'),
                ]));

                if ($exitCode !== self::SUCCESS) {
                    throw new \RuntimeException("migrate exited with code {$exitCode}.");
                }
            } catch (\Throwable $th) {
                $failed[] = $tenant->name;
                $this->components->error("{$tenant->name}: {$th->getMessage()}");
            }
        }

        $this->restoreConnection($originalDefault);

        $this->newLine();

        if (! empty($failed)) {
            $this->error('Failed for: ' . implode(', ', $failed));

            return self::FAILURE;
        }

        $this->info('Migrations completed for all tenants.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    protected function tenants(): Collection
    {
        $needle = $this->option('tenant');

        if (blank($needle)) {
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
     * Mirrors TenantBaselineMigrations rather than makeCurrent(): no tenant
     * aware task should fire while we are only running schema changes, and the
     * Tenant model reads from the landlord connection either way.
     */
    protected function useTenantConnection(Tenant $tenant): void
    {
        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    protected function restoreConnection(string $originalDefault): void
    {
        config(['database.connections.tenant.database' => null]);
        DB::setDefaultConnection($originalDefault);
        DB::purge('tenant');
    }
}
