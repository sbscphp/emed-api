<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The long term conditions a patient keeps on their own profile.
     *
     * Deliberately not medical_histories. That table is the clinical record: a
     * doctor writes it during a consultation, it hangs off a consultation id and
     * a visit number, and nothing a patient types should end up in it or be able
     * to edit what is already there. This is the patient's own list — what they
     * would tell a nurse they live with — and the two are kept apart so neither
     * can be mistaken for the other.
     */
    public function up(): void
    {
        Schema::create('patient_medical_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->string('name');
            // A month and a year is all a patient remembers, so the day is not
            // asked for and the first of the month is stored.
            $table->date('diagnosed_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('source')->default('Patient')->comment('Patient, Hospital');
            $table->unsignedBigInteger('recorded_by')->nullable()
                ->comment('users.id on the landlord connection');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_medical_conditions');
    }
};
