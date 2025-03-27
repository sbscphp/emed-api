<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
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
