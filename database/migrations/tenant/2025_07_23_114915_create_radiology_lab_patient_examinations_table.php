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
        //   'examination',
        //     'result',
        //     'unit',
        //     'normal_values',
        Schema::create('radiology_lab_patient_examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radiology_lab_patients_id')->nullable()->constrained('id')->on('radiology_lab_patients')->onDelete('cascade');
            $table->string('examination')->nullable();
            $table->integer('result')->default(0)->nullable();
            $table->integer('unit')->default(0)->nullable();
            $table->integer('normal_values')->default(0)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radiology_lab_patient_examinations');
    }
};
