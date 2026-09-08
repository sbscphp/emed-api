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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_uuid')->nullable()->index();
            $table->string('appointment_no')->nullable()->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('visit_id')->nullable()->index();
            $table->string('appointment_type')->comment('Consultation, Follow up');
            $table->unsignedBigInteger('department_id')->index();
            $table->unsignedBigInteger('doctor_id')->index()->comment('users.id on the landlord connection');
            $table->string('visit_type')->comment('In patient, Virtual, Tele consultation');
            $table->date('date');
            $table->time('time');
            $table->string('duration')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('Scheduled')
                ->comment('Scheduled, Checked In, Completed, Canceled, No show');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_uuid', 'date']);
            $table->index(['tenant_uuid', 'status']);

            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
