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
        Schema::create('lab_test_results', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('patient_visit_lab_id')->constrained('patient_visit_lab')->onDelete('cascade');
            $table->string('visitno')->nullable();
            $table->string('test_name');
            $table->string('result')->nullable();
            $table->dateTime('date_recorded')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index('result');
            $table->index('visitno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_test_results');
    }
};
