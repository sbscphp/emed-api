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
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('billing_id')->nullable();
            $table->unsignedBigInteger('service_unit_id')->nullable();
            $table->unsignedBigInteger('treatment_id')->nullable();
            $table->unsignedBigInteger('lab_test_id')->nullable();
            $table->unsignedBigInteger('radiology_test_id')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('amount_paid', 10, 2)->default(0.00);
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('status')->default('Pending')->comment('Paid', 'Part Paid', 'Pending');
            $table->foreign('billing_id')->references('id')->on('billing_logs')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
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
