<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMedicationTenantInventoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('medication_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained()->onDelete('cascade');
            $table->foreignId('pharmacy_id')->constrained()->onDelete('cascade');
            $table->string('shipment_no');
            $table->string('batch_no');
            $table->date('mfg_date');
            $table->date('expiry_date');
            $table->date('date_of_shipment')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->unsignedInteger('received_qty');
            $table->unsignedInteger('current_stock')->default(0);
            $table->string('vendor');
            $table->enum('shipment_status', ['pending', 'incomplete', 'complete', 'received'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_inventory');
    }
}
