<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\BaselinesMigrations;
use Illuminate\Console\Command;

/**
 * Record landlord migrations whose tables the landlord database already has.
 *
 * The landlord database predates `database/migrations/landlord` holding its
 * schema: its foundational tables — users, roles, cache, jobs, audit logs,
 * states — were created before those files existed, so the tables are there
 * while the rows in `migrations` that say so are not.
 *
 * That makes
 *
 *     php artisan migrate --path=database/migrations/landlord --database=landlord
 *
 * fail on the first of them with "Table 'cache' already exists", and every
 * landlord migration written since is stuck behind it.
 *
 * Run this once and those create migrations are recorded as already run,
 * without executing anything, after which the migrate command above proceeds
 * normally. Safe to re-run: a migration already recorded is skipped, and a
 * migration whose tables are genuinely missing is left for migrate to create.
 *
 * @see \App\Console\Commands\TenantBaselineMigrations for the tenant equivalent.
 */
class LandlordBaselineMigrations extends Command
{
    use BaselinesMigrations;

    protected $signature = 'landlord:baseline-migrations
                            {--path=database/migrations/landlord : Migration folder to reconcile}
                            {--connection=landlord : The connection holding the landlord database}
                            {--pretend : Show what would be recorded without writing anything}';

    protected $description = 'Mark landlord migrations whose tables already exist as run, so migrate can move past them';

    public function handle(): int
    {
        $path = $this->option('path');
        $connection = $this->option('connection');
        $pretend = (bool) $this->option('pretend');

        $migrations = $this->migrationFiles($path);

        if (empty($migrations)) {
            $this->error("No migrations found in {$path}.");

            return self::FAILURE;
        }

        $this->info('Landlord: ' . config("database.connections.{$connection}.database"));

        try {
            $this->baselineConnection($connection, $migrations, $pretend);
        } catch (\Throwable $th) {
            $this->error("  Failed: {$th->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info($pretend
            ? 'Nothing was written. Re-run without --pretend to apply.'
            : "Baseline complete. Run: php artisan migrate --path={$path} --database={$connection}");

        return self::SUCCESS;
    }
}
