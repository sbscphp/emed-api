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
        Schema::create('counselling_details', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('patient_card_no')->nullable()->unique();
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->string('counsellor_name')->nullable();
            $table->string('counsellorID')->nullable();
            $table->string('session_type')->nullable();
            $table->string('means_of_session')->nullable();
            $table->boolean('schedule_a_follow_up')->default(false);
            $table->dateTime('schedule_a_follow_up_date')->nullable();
            $table->boolean('referral')->default(false);
            $table->mediumText('referral_detail')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counselling_details');
    }
};
