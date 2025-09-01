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
        Schema::create('surgeries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('consultation_id')->nullable();
            $table->string('recommended_surgery')->nullable();
            $table->date('proposed_date')->nullable();
            $table->string('surgery_type')->nullable();
            $table->string('surgery_category')->nullable();
            $table->json('team')->nullable();
            $table->string('anaesthesia_type')->nullable();
            $table->longText('preup_instructions')->nullable();
            $table->longText('postup_instructions')->nullable();
            $table->string('duration')->nullable();
            $table->string('consent_status')->nullable();
            $table->longText('additional_remarks')->nullable();
            $table->string('status')->default('Pending')->comment('Pending, Completed, Cancelled');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surgeries');
    }
};
