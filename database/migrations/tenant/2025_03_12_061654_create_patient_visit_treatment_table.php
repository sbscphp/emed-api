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
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('consultation_id')->nullable();
            $table->unsignedBigInteger('dispensed_by')->nullable();
            $table->unsignedBigInteger('drug_id')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('drug')->nullable();
            $table->string('qualifier')->nullable();
            $table->string('quantity')->nullable();
            $table->string('dosage')->nullable();
            $table->string('weight')->nullable();
            $table->string('period')->nullable();
            $table->string('duration')->nullable();
            $table->string('route')->nullable();
            $table->string('remark')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('dispensing_date')->nullable();
            $table->integer('quantity_dispensed')->default(0);
            $table->string('status')->default('Not Fulfilled')->comment('Fulfilled, Not Fulfilled');
            $table->timestamps();
            $table->softDeletes();
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
