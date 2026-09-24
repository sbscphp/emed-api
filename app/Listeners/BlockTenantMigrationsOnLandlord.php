<?php

namespace App\Listeners;

use Illuminate\Console\Events\CommandStarting;
use RuntimeException;

/**
 * Stop the tenant migration folder from being replayed into the landlord database.
 *
 * `php artisan migrate --path=database/migrations/tenant` reads the tenant
 * folder but writes to whatever connection is default — the landlord one. The
 * first migration whose table the landlord already owns aborts the run
 * ("Base table or view already exists: users"), and anything that ran before it
 * has already been written into the central schema and recorded in the
 * landlord's `migrations` table. The mistake is easy to make and tedious to
 * undo, so it is refused up front with the command that does the job.
 *
 * @see \App\Console\Commands\TenantMigrate
 */
class BlockTenantMigrationsOnLandlord
{
    /**
     * The migrate commands that accept --path and change schema.
     */
    private const GUARDED = [
        'migrate',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
    ];

    public function handle(CommandStarting $event): void
    {
        if (! in_array($event->command, self::GUARDED, true)) {
            return;
        }

        // Read from the raw token string rather than a single --path lookup:
        // --path may be repeated, and only one of them has to be the tenant
        // folder for the run to be wrong.
        $tokens = str_replace('\\', '/', (string) $event->input);

        if (! str_contains($tokens, 'migrations/tenant')) {
            return;
        }

        $tenantConnection = config('multitenancy.tenant_database_connection_name');
        $connection = $event->input->getParameterOption('--database') ?: config('database.default');

        if ($connection === $tenantConnection) {
            return;
        }

        throw new RuntimeException(
            "Refusing to run tenant migrations on the '{$connection}' connection." . PHP_EOL
            . 'Each tenant keeps its own database, so the tenant folder has to be replayed once per tenant:' . PHP_EOL . PHP_EOL
            . '    php artisan tenant:migrate                    # every tenant' . PHP_EOL
            . '    php artisan tenant:migrate --tenant=<id|name> # one tenant' . PHP_EOL . PHP_EOL
            . "To migrate the central database instead, use --path=database/migrations/landlord."
        );
    }
}
