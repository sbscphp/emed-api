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
        Schema::create('new_born_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->date('birth_date')->nullable();
            $table->time('birth_time')->nullable();
            $table->string('sex')->nullable();
            $table->string('weight')->nullable();
            $table->longText('medical_professionals')->nullable();
            $table->string('observation')->nullable();
            $table->string('placenta')->nullable();
            $table->string('medications_given')->nullable();
            $table->string('complications')->nullable();
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
        Schema::dropIfExists('new_born_details');
    }
};
