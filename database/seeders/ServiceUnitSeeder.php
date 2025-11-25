<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Schema::disableForeignKeyConstraints();

        DB::table('service_units')->delete();
        DB::statement('ALTER TABLE service_units AUTO_INCREMENT = 1');

        Schema::enableForeignKeyConstraints();

        $now = Carbon::now();
        $tenant = app('currentTenant');

        $units = [
            ['tenant_id' => $tenant->uuid, 'name' => 'Registration', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'Pharmacy', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'Consultation', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'Laboratory', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'Radiology', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('service_units')->insert($units);
    }
}
