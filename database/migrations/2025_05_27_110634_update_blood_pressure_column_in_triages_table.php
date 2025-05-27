<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('tenant')->table('triages', function (Blueprint $table) {
            $table->dropColumn(['blood_pressure_systolic', 'blood_pressure_diastolic']);
            $table->json('blood_pressure')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('triages', function (Blueprint $table) {
            $table->dropColumn('blood_pressure');
            $table->integer('blood_pressure_systolic')->nullable();
            $table->integer('blood_pressure_diastolic')->nullable();
        });
    }
};
