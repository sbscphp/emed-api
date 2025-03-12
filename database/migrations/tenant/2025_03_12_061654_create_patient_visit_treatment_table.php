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
        Schema::create('patient_visit_treatment', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedBigInteger('consultation_id')->nullable();
            $table->string('visitno');
            $table->string('lab_dept')->nullable();
            $table->string('test_name')->nullable();
            $table->string('medication')->nullable();
            $table->string('dosage')->nullable();
            $table->string('weight')->nullable();
            $table->string('period')->nullable();
            $table->string('duration')->nullable();
            $table->string('route')->nullable();
            $table->string('remark')->nullable();
            $table->string('receiptno')->nullable();
            $table->timestamps();

            $table->index('visitno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_visit_treatment');
    }
};
