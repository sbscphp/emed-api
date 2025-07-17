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

        //   'patient_id',
        // 'medication_id',
        // 'pharmacy_id',
        // 'presscribed_drug',
        // 'patient_status',
        // 'action',

        // presscribed_drug
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module_accessed')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicine__logs');
    }
};
