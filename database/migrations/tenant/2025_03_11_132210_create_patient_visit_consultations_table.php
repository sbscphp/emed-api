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
        Schema::create('patient_visit_consultations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->unsignedBigInteger('admin_id');
            $table->string('visitno');
            $table->text('complaint')->nullable();
            $table->text('complaint_history')->nullable();
            $table->text('review')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('allergy')->nullable();
            $table->string('disease_pattern')->nullable();
            $table->string('disease_type')->nullable();
            $table->enum('investigation',['laboratory','radiology','both'])->nullable();
            $table->integer('follow_up')->default(0);
            $table->dateTime('followUp_date')->nullable();
            $table->integer('referral')->default(0);
            $table->string('referral_detail')->nullable();
            $table->integer('admitted')->default(0);
            $table->timestamps();

            $table->index('visitno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_visit_consultations');
    }
};
