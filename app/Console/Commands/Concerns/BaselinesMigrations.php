<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciling a database that already holds a schema nobody recorded.
 *
 * A database provisioned before migrations drove its schema — restored from a
 * dump, or built by hand — holds the tables without holding the rows in
 * `migrations` that say so. Every `migrate` run then replays those create
 * migrations and dies on the first "table already exists", which blocks every
 * new migration behind them.
 *
 * This closes that gap the way a baseline should: a create migration whose
 * tables are all present is written into `migrations` as already run, without a
 * single statement being executed. Anything else — a migration that creates a
 * table which really is missing, or one that alters — is left alone for
 * `migrate` to run normally.
 *
 * Shared by the tenant and landlord baseline commands, which differ only in
 * which connection they point at and which folder they read.
 *
 * @see \App\Console\Commands\TenantBaselineMigrations
 * @see \App\Console\Commands\LandlordBaselineMigrations
 */
trait BaselinesMigrations
{
    /**
     * Reconcile whichever database the given connection points at.
     *
     * @param  array<string, string>  $migrations  name => path
     */
    protected function baselineConnection(string $connection, array $migrations, bool $pretend): void
    {
        if (!Schema::connection($connection)->hasTable('migrations')) {
            $this->warn('  No migrations table — run migrate on this database first.');

            return;
        }

        $db = DB::connection($connection);

        $alreadyRun = $db->table('migrations')->pluck('migration')->all();
        $batch = ((int) $db->table('migrations')->max('batch')) + 1;

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
            if (empty($tables) || !$this->allTablesExist($connection, $tables)) {
                $leftPending[] = $name;

                continue;
            }

            $toRecord[] = [
                'migration' => $name,
                'batch' => $batch,
            ];
        }

        if (empty($toRecord)) {
            $this->line('  Nothing to baseline.');
        } else {
            foreach ($toRecord as $row) {
                $this->line("  <fg=yellow>baseline</> {$row['migration']}");
            }

            if (!$pretend) {
                $db->table('migrations')->insert($toRecord);
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
     * Read from the source rather than guessed from the file name: the folders
     * hold names that do not match their table (create_registartion_services
     * builds registration_services), and a guess there would baseline a
     * migration whose table is actually missing.
     *
     * @return array<int, string>
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

    /**
     * @param  array<int, string>  $tables
     */
    protected function allTablesExist(string $connection, array $tables): bool
    {
        foreach ($tables as $table) {
            if (!Schema::connection($connection)->hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Migration file names mapped to their paths, in the order migrate runs them.
     *
     * @return array<string, string>
     */
    protected function migrationFiles(string $path): array
    {
        $files = glob(base_path($path) . '/*.php') ?: [];

        $migrations = [];
        foreach ($files as $file) {
            $migrations[basename($file, '.php')] = $file;
        }

        ksort($migrations);

        return $migrations;
    }
}
