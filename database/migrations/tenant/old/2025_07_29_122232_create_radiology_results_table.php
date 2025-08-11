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
        Schema::create('radiology_results', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_domain')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('radiology_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('examination_type')->nullable();
            $table->string('clinical_indication')->nullable();
            $table->string('technique')->nullable();
            $table->longText('findings')->nullable();
            $table->string('result_img')->nullable();
            $table->string('status')->default('Not Ready')->comment('Not Ready, Ready');
            $table->foreign('radiology_id')->references('id')->on('patient_visit_radiology')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radiology_results');
    }
};
