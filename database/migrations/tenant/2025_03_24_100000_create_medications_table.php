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
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('reg_no')->unique();
            $table->string('generic_name');
            $table->string('brand_name');
            $table->string('medicine_name');
            $table->string('medicine_type');
            $table->decimal('cost_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->string('manufacturer');
            $table->string('medicine_status')->default('Available')->comment('Available', 'About To Expire', 'Out Of Stock', 'Expired');
            $table->string('active_ingredient')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
