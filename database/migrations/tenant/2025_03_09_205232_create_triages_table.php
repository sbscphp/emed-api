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
        Schema::create('triages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->json('blood_pressure')->nullable();
            $table->integer('pulse_bpm');
            $table->decimal('sugar_level', 5, 2);
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('temperature', 4, 2);
            $table->tinyInteger('severity')->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('triages');
    }
};
