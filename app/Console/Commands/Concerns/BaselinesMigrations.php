<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciling a database that already holds a schema nobody recorded.
 *
 * A database provisioned before migrations drove its schema — restored from a
 * dump, built by hand, or copied from another tenant — holds the tables without
 * holding the rows in `migrations` that say so. Every `migrate` run then
 * replays those create migrations and dies on the first "table already exists",
 * which blocks every new migration behind them.
 *
 * This closes that gap the way a baseline should: a create migration whose
 * tables are all present, with every column it would have given them, is
 * written into `migrations` as already run, without a single statement being
 * executed. Anything else — a migration that creates a table which really is
 * missing, or one that alters — is left alone for `migrate` to run normally.
 *
 * A table that is present but does not match is neither baselined nor run: it
 * is reported with the columns it lacks. Recording it would leave the database
 * permanently short of those columns with nothing left to notice, and running
 * it only repeats "table already exists", so the mismatch is a decision for
 * whoever knows why the table is shaped that way.
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
     * Blueprint methods that define a column named by their first argument.
     */
    private const COLUMN_METHODS = [
        'bigIncrements', 'bigInteger', 'binary', 'boolean', 'char', 'date',
        'dateTime', 'dateTimeTz', 'decimal', 'double', 'enum', 'float',
        'foreignId', 'foreignUlid', 'foreignUuid', 'geometry', 'increments',
        'integer', 'ipAddress', 'json', 'jsonb', 'longText', 'macAddress',
        'mediumIncrements', 'mediumInteger', 'mediumText', 'point', 'set',
        'smallIncrements', 'smallInteger', 'string', 'text', 'time', 'timeTz',
        'timestamp', 'timestampTz', 'tinyInteger', 'tinyText', 'ulid',
        'unsignedBigInteger', 'unsignedDecimal', 'unsignedInteger',
        'unsignedMediumInteger', 'unsignedSmallInteger', 'unsignedTinyInteger',
        'uuid', 'year',
    ];

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
        $mismatched = [];

        foreach ($migrations as $name => $file) {
            if (in_array($name, $alreadyRun, true)) {
                continue;
            }

            $tables = $this->tablesCreatedBy($file);

            // Only a migration whose whole job is creating tables can be
            // baselined, and only when every one of them is already there. A
            // half applied one has to run so the missing half is created.
            if (empty($tables) || !$this->allTablesExist($connection, array_keys($tables))) {
                $leftPending[] = $name;

                continue;
            }

            $missing = $this->missingColumns($connection, $tables);

            if (!empty($missing)) {
                $mismatched[$name] = $missing;

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

        foreach ($mismatched as $name => $missing) {
            $this->line("  <fg=red>mismatch</> {$name}");

            foreach ($missing as $table => $columns) {
                $this->line("    {$table} is missing: " . implode(', ', $columns));
            }
        }

        if (!empty($mismatched)) {
            $this->warn('  The tables above exist but do not match their migration, so they were left alone.');
            $this->warn('  Add the missing columns — or drop the table if it holds nothing — then run this again.');
        }
    }

    /**
     * Every table a migration creates, mapped to the columns it gives them.
     *
     * Read from the source rather than guessed from the file name: the folders
     * hold names that do not match their table (create_registartion_services
     * builds registration_services), and a guess there would baseline a
     * migration whose table is actually missing.
     *
     * @return array<string, array<int, string>> table => columns
     */
    protected function tablesCreatedBy(string $file): array
    {
        $source = file_get_contents($file);

        // A migration that alters as well as creates is not a pure create, and
        // its alters cannot be verified from here, so it is left to migrate.
        if (preg_match('/Schema::(?:connection\([^)]*\)->)?table\s*\(/', $source)) {
            return [];
        }

        // Split on each create() so the column calls that follow are attributed
        // to the table they belong to: [prelude, table, body, table, body, ...].
        $parts = preg_split(
            '/Schema::(?:connection\([^)]*\)->)?create\s*\(\s*[\'"]([^\'"]+)[\'"]/',
            $source,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        $tables = [];

        for ($i = 1; $i < count($parts); $i += 2) {
            $tables[$parts[$i]] = $this->columnsIn($parts[$i + 1] ?? '');
        }

        return $tables;
    }

    /**
     * The columns declared in one Schema::create() body.
     *
     * @return array<int, string>
     */
    protected function columnsIn(string $body): array
    {
        preg_match_all(
            '/\$table->([a-zA-Z_]+)\s*\(\s*(?:[\'"]([^\'"]*)[\'"])?/',
            $body,
            $matches,
            PREG_SET_ORDER
        );

        $columns = [];

        foreach ($matches as $match) {
            $method = $match[1];
            $name = $match[2] ?? '';

            if (in_array($method, self::COLUMN_METHODS, true)) {
                if ($name !== '') {
                    $columns[] = $name;
                }

                continue;
            }

            // The shorthands, which name their own columns rather than take one.
            switch ($method) {
                case 'id':
                    $columns[] = $name !== '' ? $name : 'id';
                    break;
                case 'timestamps':
                case 'timestampsTz':
                case 'nullableTimestamps':
                    $columns[] = 'created_at';
                    $columns[] = 'updated_at';
                    break;
                case 'softDeletes':
                case 'softDeletesTz':
                    $columns[] = $name !== '' ? $name : 'deleted_at';
                    break;
                case 'rememberToken':
                    $columns[] = 'remember_token';
                    break;
                case 'morphs':
                case 'nullableMorphs':
                case 'uuidMorphs':
                case 'ulidMorphs':
                    if ($name !== '') {
                        $columns[] = "{$name}_type";
                        $columns[] = "{$name}_id";
                    }
                    break;
            }
        }

        return array_values(array_unique($columns));
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
     * Columns a migration would have created that the live tables do not have.
     *
     * @param  array<string, array<int, string>>  $tables  table => columns
     * @return array<string, array<int, string>> table => missing columns
     */
    protected function missingColumns(string $connection, array $tables): array
    {
        $missing = [];

        foreach ($tables as $table => $columns) {
            // Nothing parsed means nothing to compare, and existence is then
            // all the assurance available — which is what this check began as.
            if (empty($columns)) {
                continue;
            }

            $existing = array_map(
                'strtolower',
                Schema::connection($connection)->getColumnListing($table)
            );

            $absent = array_values(array_filter(
                $columns,
                fn (string $column) => !in_array(strtolower($column), $existing, true)
            ));

            if (!empty($absent)) {
                $missing[$table] = $absent;
            }
        }

        return $missing;
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
