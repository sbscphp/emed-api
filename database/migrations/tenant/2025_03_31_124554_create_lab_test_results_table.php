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
        Schema::create('lab_test_results', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('patient_visit_lab_id')->nullable();
            $table->string('visit_id')->nullable();
            $table->string('test');
            $table->string('result')->nullable();
            $table->string('reference_range')->nullable();
            $table->string('status')->nullable()->comment('Not Ready, Ready');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_test_results');
    }
};
