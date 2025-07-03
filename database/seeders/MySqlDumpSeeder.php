<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MySqlDumpSeeder extends Seeder
{
    



public function run(): void
{
//     DB::statement('USE ' . env('DB_DATABASE'));
//     DB::statement('SET SQL_SAFE_UPDATES = 0');
//     DB::statement('SET FOREIGN_KEY_CHECKS = 0');
//     DB::statement("SET SESSION sql_mode = ''");

//     $remoteUrl = 'https://verdant-nasturtium-ccf81a.netlify.app/world.sql';

//     // Download the SQL file content
//     $sql = file_get_contents($remoteUrl);
//     if ($sql === false) {
//         throw new \Exception("Failed to download SQL from: $remoteUrl");
//     }

//     // Remove versioned MySQL comments like /*!40101 SET ...
//     $sql = preg_replace('/\/\*![0-9]+ .*?\*\//s', '', $sql);

//     // Split SQL into individual statements
//     $statements = array_filter(array_map('trim', explode(';', $sql)));

//     foreach ($statements as $statement) {
//         if (!empty($statement)) {
//             DB::unprepared($statement . ';');
//         }
//     }
}

}
