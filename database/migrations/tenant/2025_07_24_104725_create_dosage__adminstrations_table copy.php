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
        Schema::create('dosage_administrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->nullable()
                ->constrained('patients')
                ->onDelete('cascade');

            $table->unsignedBigInteger('visit_id')->nullable();

            $table->string('vaccine_name')->nullable();
            $table->string('vaccine_code')->nullable()->unique();
            $table->string('dosage')->nullable();
            $table->string('weight')->nullable()->comment('Possible units: Milligram, Gram, Microgram, ml');
            $table->string('batch_number')->nullable();
            $table->date('administration_date')->nullable();
            $table->string('manufacturer')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('route_of_administration')->nullable()->comment('Possible routes: oral, intramuscular, subcutaneous, intradermal');
            $table->string('injection_site')->nullable()->comment('Possible sites: left arm, right arm, left thigh, right thigh');
            $table->string('administering_healthcare_professional')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosage_administrations');
    }
};
