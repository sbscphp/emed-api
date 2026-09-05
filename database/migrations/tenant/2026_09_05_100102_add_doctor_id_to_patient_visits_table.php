<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The consultant the record officer assigns the patient to at visit initiation.
     * Drives the consultation charge. Users live in the landlord DB, so this is a
     * plain id (no FK), matching initiated_by / consulted_by elsewhere.
     */
    public function up(): void
    {
        Schema::table('patient_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_visits', 'doctor_id')) {
                $table->unsignedBigInteger('doctor_id')->nullable()->after('service_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patient_visits', function (Blueprint $table) {
            $table->dropColumn('doctor_id');
        });
    }
};
