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
        Schema::create('consultation__details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('cascade');
            $table->foreignId('patient_visits_id')->nullable()->constrained('patient_visits')->onDelete('cascade');
            $table->string('complaints')->nullable();
            $table->string('history_of_present_complaints')->nullable();
            $table->string('system_view')->nullable();
            $table->string('provisional_diagnosis')->nullable();
            $table->string('disease_patterns')->nullable();
            $table->string('disease_types')->nullable();
            $table->string('allergies')->nullable();
            $table->boolean('laboratory')->default(false)->nullable();
            $table->boolean('radiology')->default(false)->nullable();
            $table->boolean('both')->default(false)->nullable();
            $table->boolean('schedule_a_follow_up')->default(false)->nullable();
            $table->boolean('referral')->default(false)->nullable();

            $table->string('relationship_type')->nullable();
            $table->string('chronic_lllness')->nullable();
            $table->integer('age_of_onset')->nullable();
            $table->string('causes_of_death_in_family_member')->nullable();
            $table->string('other_details')->nullable();

            $table->string('occupation')->nullable();
            $table->string('living_situation')->nullable();
            $table->string('substance_use')->nullable();
            $table->string('lifesytle_habits')->nullable();
            $table->string('sexual_history')->nullable();
            $table->boolean('admit_patient')->default(false)->nullable();

            $table->timestamps();
        });



        // 'occupation',
        // 'living_situation',
        // 'substance_use',
        // 'lifesytle_habits',
        // 'sexual_history',

        // 'admit_patient'
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation__details');
    }
};
