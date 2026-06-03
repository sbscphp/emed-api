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
        $this->call([
            // PermissionTableSeeder::class,
            // RolePermissionSeeder::class,
            // ServicesTableSeeder::class,
            // ServiceUnitSeeder::class,
            // StateSeeder::class,
            // ServiceCategorySeeder::class,
            // LabParameterSeeder::class,
            // LabTestSeeder::class,
            RadiologyTestSeeder::class,
            // UsersTableSeeder::class,
            // TenantUserSeeder::class,
        ]);

        // $this->call(MySqlDumpSeeder::class);
    }
}
