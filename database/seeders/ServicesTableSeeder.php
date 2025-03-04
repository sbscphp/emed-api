<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            ['name' => 'GOPD'],
            ['name' => 'SOPD'],
            ['name' => 'IMMUNIZATION'],
            ['name' => 'HIV/AIDS'],
            ['name' => 'ANTENATAL'],
        ];

        DB::table('services')->insert($services);

    }
}
