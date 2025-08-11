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


        Schema::create('dosage__adminstrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('cascade');
            $table->string('vaccine_name')->nullable();
            $table->string('vaccine_code')->unique()->nullable();
            $table->string('dosage')->nullable();
            $table->string('weight')->nullable()->comment('Milligram', 'Grams', 'Mircogram', 'Mis');
            $table->string('batch_number')->nullable();
            $table->date('administration_date')->nullable();
            $table->string('manufacturer')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('route_of_adminstration')->nullable();
            $table->string('route_of_administration')->nullable()->comment('oral', 'intramuscular', 'subcutaneous', 'intradermal');
            $table->string('injection_site')->nullable()->comment('left arm', 'right arm', 'left thigh', 'right thigh');
            $table->string('administering_healthcare_professional')->nullable();
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
