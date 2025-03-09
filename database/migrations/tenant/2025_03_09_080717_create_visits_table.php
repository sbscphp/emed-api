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
        Schema::create('visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->dateTime('arrival_time');
            $table->dateTime('departure_time')->nullable();
            $table->string('status');
            $table->enum('visit_type', ['initial','follow up']);
            $table->dateTime('visit_date')->nullable();
            $table->timestamps();

            $table->index('patient_id');
            $table->index('arrival_time');
            $table->index('status');
            $table->index('visit_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
