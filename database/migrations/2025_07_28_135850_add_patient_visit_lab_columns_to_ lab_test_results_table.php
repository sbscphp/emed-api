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
        Schema::table('patient_visit_lab', function (Blueprint $table) {
            $table->enum('status', ['complete', 'in progress', 'pending'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(' lab_test_results', function (Blueprint $table) {
            //
        });
    }
};
