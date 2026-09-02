<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record migrations whose tables a tenant database already has.
 *
 * Tenant databases provisioned before migrations drove the schema (restored
 * from a dump, or created by hand) hold the tables without holding the rows in
 * their `migrations` table that say so. Every `migrate` run then replays those
 * create migrations and dies on the first "table already exists", which blocks
 * every new migration behind them.
 *
 * This command closes that gap the way a baseline should: a create migration
 * whose tables are all present is written into `migrations` as already run,
 * without executing a single statement. Anything else — a migration that
 * creates a table which really is missing, or one that only alters tables — is
 * left alone for `migrate` to run normally.
 */
class TenantBaselineMigrations extends Command
{
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
        $migrations = $this->migrationFiles();

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
                $this->baselineTenant($migrations, $pretend);
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
     * Reconcile the tenant the connection currently points at.
     */
    protected function baselineTenant(array $migrations, bool $pretend): void
    {
        $connection = DB::connection('tenant');

        if (!Schema::connection('tenant')->hasTable('migrations')) {
            $this->warn('  No migrations table — run migrate on this tenant first.');

            return;
        }

        $alreadyRun = $connection->table('migrations')->pluck('migration')->all();
        $batch = ((int) $connection->table('migrations')->max('batch')) + 1;

        $toRecord = [];
        $leftPending = [];

        foreach ($migrations as $name => $file) {
            if (in_array($name, $alreadyRun, true)) {
                continue;
            }

            $tables = $this->tablesCreatedBy($file);

            // Only a migration whose whole job is creating tables can be
            // baselined, and only when every one of them is already there. A
            // half applied one has to run so the missing half is created.
            if (empty($tables) || !$this->allTablesExist($tables)) {
                $leftPending[] = $name;

                continue;
            }

            $toRecord[] = [
                'migration' => $name,
                'batch'     => $batch,
            ];
        }

        if (empty($toRecord)) {
            $this->line('  Nothing to baseline.');
        } else {
            foreach ($toRecord as $row) {
                $this->line("  <fg=yellow>baseline</> {$row['migration']}");
            }

            if (!$pretend) {
                $connection->table('migrations')->insert($toRecord);
            }

            $this->line('  <fg=green>' . count($toRecord) . ' migration(s) recorded as already run (batch ' . $batch . ').</>');
        }

        foreach ($leftPending as $name) {
            $this->line("  <fg=cyan>will run</> {$name}");
        }
    }

    /**
     * Every table a migration creates, read from its Schema::create() calls.
     *
     * Read from the source rather than guessed from the file name: the folder
     * holds names that do not match their table (create_registartion_services
     * builds registration_services), and a guess there would baseline a
     * migration whose table is actually missing.
     */
    protected function tablesCreatedBy(string $file): array
    {
        $source = file_get_contents($file);

        // A migration that alters as well as creates is not a pure create, and
        // its alters cannot be verified from here, so it is left to migrate.
        if (preg_match('/Schema::(?:connection\([^)]*\)->)?table\s*\(/', $source)) {
            return [];
        }

        preg_match_all(
            '/Schema::(?:connection\([^)]*\)->)?create\s*\(\s*[\'"]([^\'"]+)[\'"]/',
            $source,
            $matches
        );

        return array_unique($matches[1]);
    }

    protected function allTablesExist(array $tables): bool
    {
        foreach ($tables as $table) {
            if (!Schema::connection('tenant')->hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Migration file names mapped to their paths, in the order migrate runs them.
     */
    protected function migrationFiles(): array
    {
        $files = glob(base_path($this->option('path')) . '/*.php') ?: [];

        $migrations = [];
        foreach ($files as $file) {
            $migrations[basename($file, '.php')] = $file;
        }

        ksort($migrations);

        return $migrations;
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
