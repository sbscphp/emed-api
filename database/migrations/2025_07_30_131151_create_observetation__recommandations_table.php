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
        Schema::create('observetation__recommandations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('cascade');
            $table->string('patient_name')->nullable();
            $table->integer('patient_card_name')->nullable();
            $table->date('date_of_session')->nullable();
            $table->string('time_of_session')->nullable(); // Consider using `time` type if appropriate
            $table->string('counsellor_name')->nullable();
            $table->string('counsellor_id')->nullable(); // Use unsignedBigInteger if this links to users table
            $table->string('session_type')->nullable();
            $table->string('means_of_session')->nullable();
            $table->boolean('schedule_a_follow')->nullable();
            $table->date('schedule_date')->nullable();
            $table->boolean('referral')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }






    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('observetation__recommandations');
    }
};
