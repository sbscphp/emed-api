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
        Schema::table('radiology_lab_patients', function (Blueprint $table) {
            $table->foreignId('patient_visits_id')->nullable()->constrained('patient_visits')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('radiology_lab_patients', function (Blueprint $table) {
            //
        });
    }
};
