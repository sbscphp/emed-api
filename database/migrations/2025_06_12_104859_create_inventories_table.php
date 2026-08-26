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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no');
            $table->string('item_name');
            $table->unsignedBigInteger('medicine_type_id')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('reorder_level');
            $table->string('supplier')->nullable();
            $table->date('expiry_date');
            $table->text('note')->nullable();
            $table->enum('status', ['Expired', 'Low Stock', 'Sufficient'])->default('Sufficient');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
