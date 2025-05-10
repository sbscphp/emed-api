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

        $units = [
            ['name' => 'Records', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pharmacy', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Consultation', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Laboratory', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('service_units')->insert($units);
    }
}
