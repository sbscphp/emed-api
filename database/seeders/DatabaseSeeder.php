<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if ($currentTenant) {
            $this->call([
                PermissionTableSeeder::class,
                RolePermissionSeeder::class,
                ServicesTableSeeder::class,
                ServiceUnitSeeder::class,
                ServiceCategorySeeder::class,
                DepartmentSeeder::class,
                LabParameterSeeder::class,
                LabTestSeeder::class,
                // Must precede RadiologyTestSeeder: the tests are filed under
                // the categories it creates.
                RadiologyCategorySeeder::class,
                RadiologyTestSeeder::class,
                BillingServiceSeeder::class,
                StateSeeder::class,
                // UsersTableSeeder::class,
                // TenantUserSeeder::class,
            ]);

            return;
        }

        $this->call([
            SuperAdminPermissionSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // $this->call(MySqlDumpSeeder::class);
    }
}
