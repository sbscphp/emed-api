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
        Schema::create('antenatal_lab_tests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->boolean('vdrl')->default(false);
            $table->boolean('hcv')->default(false);
            $table->boolean('genotype')->default(false);
            $table->boolean('hbsag')->default(false);
            $table->boolean('hiv')->default(false);
            $table->boolean('blood_group')->default(false);
            $table->boolean('hbgd')->default(false);
            $table->boolean('pcv')->default(false);
            $table->boolean('cvs')->default(false);
            $table->boolean('rs')->default(false);
            $table->boolean('spleen')->default(false);
            $table->boolean('liver')->default(false);
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
        Schema::dropIfExists('antenatal_lab_tests');
    }
};
