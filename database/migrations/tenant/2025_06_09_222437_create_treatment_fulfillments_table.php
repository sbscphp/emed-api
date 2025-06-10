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
        Schema::create('treatment_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('treatment_id');
            $table->string('dispensing_pharmacist');
            $table->date('dispensing_date');
            $table->integer('quantity_dispensed');
            $table->string('batch_number');
            $table->date('expiry_date');
            $table->string('prescription_status');
            // $table->string('license_number');
            // $table->string('owner_manager');
            $table->string('payment_status'); // Paid, Pending, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_fulfillments');
    }
};
