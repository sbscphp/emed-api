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
        Schema::create('drug_charts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('drug_id')->nullable();
            $table->unsignedBigInteger('administered_by')->nullable();
            $table->string('drug_name')->nullable();
            $table->string('dosage')->nullable();
            $table->string('route')->nullable();
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->date('start_date')->nullable();
            $table->string('administration')->nullable();
            $table->longText('notes')->nullable();
            $table->string('status')->default('Pending')->comment('Pending, Administered, Cancelled');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drug_charts');
    }
};
