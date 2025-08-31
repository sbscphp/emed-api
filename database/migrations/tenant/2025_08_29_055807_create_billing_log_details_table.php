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
        Schema::create('billing_log_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_id')->nullable();
            $table->unsignedBigInteger('service_unit_id')->nullable();
            $table->unsignedBigInteger('drug_id')->nullable();
            $table->unsignedBigInteger('lab_service_id')->nullable();
            $table->unsignedBigInteger('radiology_service_id')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('quantity')->default(1)->nullable();
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('status')->default('Pending')->comment('Paid', 'Part Paid', 'Pending');
            $table->foreign('billing_id')->references('id')->on('billing_logs')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_log_details');
    }
};
