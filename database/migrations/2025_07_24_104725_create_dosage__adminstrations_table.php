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

        //     "vaccine_name",
        // "vaccine_code",
        // "dosage",
        // "weight",
        // "batch_number",
        // "administration_date",
        // "manufacturer",
        // "expiration_date",
        // "route_of_adminstration",
        // "injection_site",
        // "administration_date",
        // "manufacturer",
        // "expiration_date",
        // "route_of_administration",
        // "injection_site",
        // "administering_health_professional"
        Schema::create('dosage__adminstrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('cascade');
            $table->string('vaccine_name')->nullable();
            $table->string('dosage')->nullable();
            $table->enum('weight', ['Milligram', 'Grams', 'Mircogram', 'Mis'])->nullable();
            $table->string('batch_number')->nullable();
            $table->date('administration_date')->nullable();
            $table->string('manufacturer')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('route_of_adminstration')->nullable();
            $table->string('injection_site')->nullable();
            $table->string('manufacturer')->nullable();
            $table->enum('route_of_administration', ['oral', 'intramuscular', 'subcutaneous', 'intradermal'])->nullable();
            $table->enum('injection_site', ['left arm', 'right arm', 'left thigh', 'right thigh'])->nullable();
            $table->string('administering_health_professional')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosage__adminstrations');
    }
};
