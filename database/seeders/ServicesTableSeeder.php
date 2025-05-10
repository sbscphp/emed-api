<?php

namespace Database\Seeders;

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

        $services = [
            ['name' => 'GOPD'],
            ['name' => 'SOPD'],
            ['name' => 'IMMUNIZATION'],
            ['name' => 'HIV/AIDS'],
            ['name' => 'ANTENATAL'],
        ];

        DB::table('services')->insert($services);

        $this->command->info('Services seeded successfully.');
    }
}
