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
        Schema::create('pharmacy_supplies', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('product_name')->nullable();
            $table->string('product_category')->nullable();
            $table->integer('quantity_supplied')->nullable();
            $table->string('stock_level')->nullable();
            $table->string('supplier_name')->nullable();
            $table->date('supplied_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharmacy_supplies');
    }
};
