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
        Schema::create('patient_visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('visitno')->nullable();
            $table->string('status')->nullable()->comment('Visit Initiated', 'Ongoing', 'Completed');
            $table->string('triage_status')->nullable()->comment('Pending', 'Completed');
            $table->string('immunization_status')->nullable()->comment('Pending', 'Completed');
            $table->string('counsel_status')->nullable()->comment('Pending', 'Completed');
            $table->string('natal_status')->nullable()->comment('Pending', 'Completed');
            $table->string('con_status')->nullable()->comment('Pending', 'Completed');
            $table->string('pharm_status')->nullable()->comment('Pending', 'Completed');
            $table->string('lab_status')->nullable()->comment('Pending', 'Completed');
            $table->string('rad_status')->nullable()->comment('Pending', 'Completed');
            $table->dateTime('arrival_date')->nullable();
            $table->dateTime('departure_date')->nullable();
            $table->dateTime('visit_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_visits');
    }
};
