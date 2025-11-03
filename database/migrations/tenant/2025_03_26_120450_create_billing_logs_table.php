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
        Schema::create('billing_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('service_type_id')->nullable();
            $table->unsignedBigInteger('service_unit_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('patient_name')->nullable();
            $table->date('billing_date')->nullable();
            $table->integer('quantity')->default(1)->nullable();
            $table->decimal('amount_paid', 12, 2)->default(0.00);
            $table->decimal('amount_outstanding', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('grand_total', 12, 2)->default(0.00);
            $table->string('payment_method')->comment('Bank Transfer', 'Credit Card', 'Cash', 'Pos', 'Insurance')->nullable();
            $table->string('payment_status')->default('Pending')->comment('Paid', 'Part Paid', 'Pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_logs');
    }
};
