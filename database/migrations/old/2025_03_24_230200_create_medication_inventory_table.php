<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_inventory', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('shipment_no')->nullable();
            $table->string('batch_no')->nullable();
            $table->string('product_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('active_ingredient')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('courier_service')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('order_placed_by')->nullable();
            $table->string('delivery_location')->nullable();
            $table->date('dispatched_date')->nullable();
            $table->string('current_location')->nullable();
            $table->mediumText('delivery_note')->nullable();
            $table->date('date_of_shipment')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->integer('received_qty')->default(0);
            $table->integer('current_stock')->default(0);
            $table->string('support_doc')->nullable();
            $table->string('support_file')->nullable();
            $table->string('shipment_status')->default('Pending')->comment('Pending', 'Incomplete', 'Complete', 'Received');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_inventory');
    }
};
