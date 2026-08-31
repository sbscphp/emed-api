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

        Schema::create('immunizations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->boolean('schedule_a_follow_up')->default(false);
            $table->dateTime('schedule_a_follow_up_date')->nullable();
            $table->boolean('referral')->default(false);
            $table->mediumText('referral_detail')->nullable();
            $table->string('immunization_type')->nullable()->comment(
                'Possible values: COVID-19 Vaccine, Hepatitis B Vaccine, Polio Vaccine, Measles Vaccine, BCG (Tuberculosis Vaccine)'
            );

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('immunizations');
    }
};
