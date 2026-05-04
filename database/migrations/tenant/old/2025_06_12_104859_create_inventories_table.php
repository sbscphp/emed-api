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
            $table->unsignedBigInteger('medication_id')->nullable();
            $table->unsignedBigInteger('medicine_type_id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('batch_no')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->string('supplier')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('Sufficient')->comment('Expired', 'Low Stock', 'Sufficient');
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
