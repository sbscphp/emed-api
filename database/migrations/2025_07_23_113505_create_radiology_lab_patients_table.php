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
        //       'patient_id',
        // 'test_name',
        // 'user_id'
        Schema::create('radiology_lab_patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('id')->on('patients')->onDelete('cascade');
            $table->string('test_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radiology_lab_patients');
    }
};
