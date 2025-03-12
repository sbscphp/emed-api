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
            $table->foreignId('admin_id')->constrained('user_id')->onDelete('cascade');
            $table->string('visitno');
            $table->text('complaints')->nullable();
            $table->text('complaint_history')->nullable();
            $table->text('review')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('allergy')->nullable();
            $table->string('disease_pattern')->nullable();
            $table->string('disease_type')->nullable();
            $table->enum('investigation',['laboratory','radiology','both'])->nullable();
            $table->enum('follow_up',[true,false])->default(false);
            $table->dateTime('followUp_date')->nullable();
            $table->enum('referral',[true,false])->default(false);
            $table->string('referral_detail')->nullable();
            $table->enum('admitted',[true,false])->default(false);
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
