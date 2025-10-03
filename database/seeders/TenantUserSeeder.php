<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class TenantUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (env('APP_ENV') == 'local') {
            $tenants = [
                [
                    'name' => "SBSC Hospital",
                    'uuid' => (string) Str::uuid(),
                    'domain' => "sbscuk.co.hospital",
                    'database' => "tenant_sbsc_hospital",
                    'state_city' => 'Lagos State',
                    'registration_number' => '123456789',
                    'email' => 'emed@gamil.com',
                    'phone_number' => '08067799281',
                    'address' => 'No 11, Emed street, Lagos',
                    'theme_color' => '#0d6efd',
                    'logo' => NULL,
                    'license' => NULL,
                ],
            ];
        } else {
            $tenants = [
                [
                    'name' => "Emed Tenant",
                    'uuid' => (string) Str::uuid(),
                    'domain' => "emed_tenant.co.uk",
                    'database' => 'jkpmjemy_emed_tenant_dev',
                    'state_city' => 'Lagos State',
                    'registration_number' => '123456789',
                    'email' => 'emed@gamil.com',
                    'phone_number' => '08067799281',
                    'address' => 'No 11, Emed street, Lagos',
                    'theme_color' => '#0d6efd',
                    'logo' => NULL,
                    'license' => NULL,
                ],
            ];
        }

        foreach ($tenants as $tenantData) {
            DB::beginTransaction();
            try {

                $existingTenant = Tenant::where('domain', $tenantData['domain'])->first();

                if ($existingTenant) {
                    $this->command->info("Tenant {$tenantData['name']} already exists. Skipping...");
                    continue;
                }

                $tenant = Tenant::create($tenantData);

                $this->command->info("Created tenant: {$tenant->name}");

                if (env('APP_ENV') == 'local') {
                    DB::statement("CREATE DATABASE IF NOT EXISTS {$tenant->database}");
                    $this->command->info("Database {$tenant->database} created successfully.");


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

                    $tenant->forget();
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->info("Error setting up tenant {$tenantData['name']}: " . $e->getMessage());
            }
        }
    }
}
