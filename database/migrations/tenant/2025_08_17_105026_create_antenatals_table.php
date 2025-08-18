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
        Schema::create('antenatals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->date('booking_date')->nullable();
            $table->date('last_period_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->string('pregnancy_duration')->nullable();
            $table->string('bleeding')->nullable();
            $table->string('urinary_symptoms')->nullable();
            $table->string('vaginal_discharge')->nullable();
            $table->string('other_symptoms')->nullable();
            $table->boolean('constipation')->default(false);
            $table->boolean('headache')->default(false);
            $table->boolean('vomiting')->default(false);
            $table->boolean('oedema')->default(false);
            $table->boolean('fmf')->default(false);
            $table->string('height')->nullable();
            $table->string('weight')->nullable();
            $table->integer('skin')->nullable();
            $table->string('general_conditions')->nullable();
            $table->string('malnutrition')->nullable();
            $table->string('clinical_anaemia')->nullable();
            $table->string('liver', 15, 2)->nullable();
            $table->string('spleen')->nullable();
            $table->string('breast')->nullable();
            $table->string('other_abnormalities')->nullable();
            $table->string('vaginal_examination')->nullable();
            $table->string('tetanus_toxoid')->nullable();
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('antenatals');
    }
};
