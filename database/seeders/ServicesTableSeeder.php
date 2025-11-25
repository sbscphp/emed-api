<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Cleaning up services table...');

        Schema::disableForeignKeyConstraints();

        DB::table('services')->delete();
        DB::statement('ALTER TABLE services AUTO_INCREMENT = 1');

        Schema::enableForeignKeyConstraints();
        $tenant = app('currentTenant');
        $now = Carbon::now();

        $services = [
            ['tenant_id' => $tenant->uuid, 'name' => 'GOPD', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'SOPD', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'IMMUNIZATION', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'HIV/AIDS', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'ANTENATAL', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('services')->insert($services);

        $this->command->info('Services seeded successfully.');
    }
}
