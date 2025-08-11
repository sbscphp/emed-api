<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patient_visits', function (Blueprint $table) {
            DB::statement("ALTER TABLE patient_visits MODIFY status status ENUM('ongoing', 'waiting', 'completed', 'missed') NOT NULL");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_visits', function (Blueprint $table) {
            DB::statement("ALTER TABLE patient_visits MODIFY status status ENUM('ongoing', 'waiting', 'completed', 'missed') NOT NULL");
        });
    }
};
