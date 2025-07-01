<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MySqlDumpSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
     $sqlPath = database_path('sql/world.sql');

        if (!File::exists($sqlPath)) {
            throw new \Exception("SQL file not found at: $sqlPath");
        }

        $sql = File::get($sqlPath);
        DB::unprepared($sql);
    }
}
