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
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('cascade');
            $table->boolean('schedule_a_follow_up')->default(false)->nullable();
            $table->date('schedule_a_follow_up_date')->nullable();
            $table->boolean('referral')->default(false)->nullable();
            $table->enum('immunization_type', [
                'COVID-19 Vaccine',
                'Hepatitis B Vaccine',
                'Polio Vaccine',
                'Measles Vaccine',
                'BCG (Tuberculosis Vaccine)'
            ])->nullable();
            $table->mediumText('referral_detail')->nullable();
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
