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
        Schema::create('medicine__logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained('id')->on('patients')->onDelete('cascade');
            $table->foreignId('medication_id')->nullable()->constrained('id')->on('medications')->onDelete('cascade');
            $table->foreignId('pharmacy_id')->nullable()->constrained('id')->on('pharmacies')->onDelete('cascade');
            $table->string('presscribed_drug')->nullable();
            $table->string('patient_status')->nullable();
            $table->string('status')->nullable();
            $table->string('action')->nullable();
            // arrival_date visitno
            $table->string('visitno')->nullable();
            $table->date('arrival_date')->nullable();
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
