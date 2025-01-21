<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class TenantUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = [
            [
                'name' => "SBSCUK",
                'domain' => "sbscuk.co.uk",
                'database' => "tenant_sbscuk",
            ]
        ];

        foreach ($tenants as $tenantData) {
            DB::beginTransaction();
            try {
                // Check if the tenant already exists to avoid duplication
                $existingTenant = Tenant::where('domain', $tenantData['domain'])->first();

                if ($existingTenant) {
                    $this->command->info("Tenant {$tenantData['name']} already exists. Skipping...");
                    continue;
                }

                // Create the tenant record
                $tenant = Tenant::create($tenantData);

                $this->command->info("Created tenant: {$tenant->name}");

                // Automatically create database for the tenant
                DB::statement("CREATE DATABASE IF NOT EXISTS {$tenant->database}");
                $this->command->info("Database {$tenant->database} created successfully.");

                // Switch to the tenant and run migrations
                $tenant->makeCurrent();

                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true
                ]);
                $this->command->info("Migrations executed for tenant: {$tenant->name}");

                $seedingExitCode = Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => 'DatabaseSeeder',
                    '--force' => true,
                    '--verbose' => true,
                ]);
                
                $output = Artisan::output();
                $this->command->info("Seeding output for {$tenant->name}: $output");
                
                if ($seedingExitCode !== 0) {
                    $this->command->error("Seeding failed for tenant: {$tenant->name}");
                    throw new \Exception("Seeding failed for tenant: {$tenant->name}");
                }
                
                $this->command->info("Seeded default data for tenant: {$tenant->name}");

                // Forget current tenant
                $tenant->forget();

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->info("Error setting up tenant {$tenantData['name']}: " . $e->getMessage());
            }
        }
    }
}
