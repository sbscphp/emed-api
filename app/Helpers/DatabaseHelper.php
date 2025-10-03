<?php

use Illuminate\Support\Facades\DB;

if (!function_exists('schemaHasDatabase')) {
    function schemaHasDatabase($dbname)
    {
        // Escape backticks to prevent SQL injection
        $dbname = addslashes($dbname);
        $result = DB::select("SHOW DATABASES LIKE '{$dbname}'");
        return !empty($result);
    }
}