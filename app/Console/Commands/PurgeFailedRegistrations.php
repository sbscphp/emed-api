<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Clean up the records left behind by hospital registrations that failed half
 * way through before the rollback in AuthenticationService was in place.
 *
 * Those leftovers keep holding the unique indexes on tenants.domain /
 * tenants.database and users.email, so the same hospital can never be
 * registered again.
 */
class PurgeFailedRegistrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'registrations:purge-failed {--force : Actually delete the records instead of only listing them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove tenants and users left behind by hospital registrations that never completed';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $apply = (bool) $this->option('force');

        if (! $apply) {
            $this->warn('Dry run: nothing is deleted. Re-run with --force to apply.');
        }

        $tenants = $this->staleTenants();
        $this->report('Stale hospitals (deleted, database no longer exists)', $tenants->map(
            fn(Tenant $tenant) => "#{$tenant->id} {$tenant->name} <{$tenant->domain}>"
        ));

        if ($apply) {
            $tenants->each(function (Tenant $tenant) {
                TenantUser::withTrashed()->where('tenant_id', $tenant->id)->forceDelete();
                $tenant->forceDelete();
            });
        }

        $users = $this->orphanUsers();
        $this->report('Orphan admins (no hospital, never verified)', $users->map(
            fn(User $user) => "#{$user->id} {$user->email}"
        ));

        if ($apply) {
            $users->each(function (User $user) {
                DB::connection($this->landlordConnection())
                    ->table('password_reset_tokens')
                    ->where('email', $user->email)
                    ->delete();

                $user->forceDelete();
            });

            $this->info('Done.');
        }

        return self::SUCCESS;
    }

    /**
     * Soft deleted tenants whose database is gone. Removing a client keeps its
     * database around, so a missing database means the registration itself
     * never finished.
     */
    private function staleTenants()
    {
        $databases = DB::connection($this->landlordConnection())
            ->select('SELECT SCHEMA_NAME AS name FROM INFORMATION_SCHEMA.SCHEMATA');

        $existing = collect($databases)->pluck('name')->all();

        return Tenant::onlyTrashed()->get()->filter(
            fn(Tenant $tenant) => ! in_array($tenant->database, $existing, true)
        )->values();
    }

    /**
     * Users that belong to no (live) hospital, hold no super admin role and
     * never verified their email address.
     */
    private function orphanUsers()
    {
        return User::withTrashed()
            ->whereDoesntHave('tenants')
            ->whereDoesntHave('superAdminRoles')
            ->whereNull('email_verified_at')
            ->where(fn($query) => $query->whereNull('is_verified')->orWhere('is_verified', 0))
            ->get();
    }

    private function report(string $title, $lines): void
    {
        $this->line('');
        $this->info("{$title}: {$lines->count()}");

        $lines->each(fn($line) => $this->line("  - {$line}"));
    }

    private function landlordConnection(): string
    {
        return config('multitenancy.landlord_database_connection_name', 'landlord');
    }
}
