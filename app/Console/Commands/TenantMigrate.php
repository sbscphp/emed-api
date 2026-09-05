<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migration for tenant database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //Get all members
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error("No tenants found.");
            return;
        }

        foreach ($tenants as $tenant){
            $this->info("Migrating database for tenant: {$tenant->name}");

            try {
                //$tenant->makeCurrent();

                config(['database.connections.tenant.database' => $tenant->database]);
                DB::purge('tenant');
                DB::reconnect('tenant');
                DB::setDefaultConnection('tenant');

                $this->info("Current database connection: " . DB::connection()->getName());
                $this->info("Current database name: " . DB::connection()->getDatabaseName());

                $this->info("Tenant database configuration: " . json_encode($tenant->database));
                $output = [];
                Artisan::call('migrate', [
                    '--path' => 'database/migrations/tenant', // Specify the tenant migrations folder
                    '--force' => true, // Run non-interactively (required under APP_ENV=production)
                ], $output);

                // Display the output of the migrate command
               // $this->info("Migration output for tenant {$tenant->name}:");
                $this->line(implode("\n", $output));

                $this->info("Migrations completed for tenant: {$tenant->name}");
            } catch (\Throwable $th) {
                $this->error("Error during migration for tenant {$tenant->name}: " . $th->getMessage());
            }finally {
                // Revert to the landlord database connection
                config(['database.default' => 'landlord']);
                DB::purge('landlord');
                DB::reconnect('landlord');
                DB::setDefaultConnection('landlord');
            }

            $this->line("----------------------------------------");
        }

        $this->info("Migrations completed for all tenants.");
    }
}
