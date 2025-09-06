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
        Schema::create('pharmacy_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('supplied_by')->nullable();
            $table->foreignId('pharmacy_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('inventory_id')->nullable();
            $table->date('requested_date')->nullable();
            $table->string('urgency_level')->nullable(); // e.g. low, medium, high
            $table->string('product')->nullable();
            $table->string('category')->nullable();
            $table->integer('quantity_requested');
            $table->string('reason_for_request')->nullable();
            $table->integer('quantity_supplied')->default(0);
            $table->integer('quantity_available')->default(0);
            $table->integer('quantity_dispensed')->default(0);
            $table->string('stock_level')->nullable()->comment('e.g. In stock, Out Of Stock, Low Stock');
            $table->string('batch_number')->nullable();
            $table->date('supplied_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharmacy_requests');
    }
};
