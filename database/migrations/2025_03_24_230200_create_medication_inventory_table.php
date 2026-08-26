<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMedicationInventoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('medication_inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medication_id');
            $table->unsignedBigInteger('pharmacy_id');
            $table->unsignedBigInteger('vendor_id');
            $table->string('shipment_no');
            $table->string('batch_no');
            $table->date('mfg_date');
            $table->date('expiry_date');
            $table->date('date_of_shipment')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->unsignedInteger('received_qty');
            $table->unsignedInteger('current_stock')->default(0);
            $table->string('vendor')->nullable();
            $table->enum('shipment_status', ['pending', 'incomplete', 'complete', 'received'])->default('pending');
            $table->foreign('pharmacy_id')->references('id')->on('pharmacies')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('medication_id')->references('id')->on('medications')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_inventory');
    }
}
