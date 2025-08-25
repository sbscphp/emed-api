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

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('patient_name')->nullable();

            $table->date('billing_date')->nullable();

            $table->unsignedBigInteger('service_type_id')->nullable();
            $table->unsignedBigInteger('service_unit_id')->nullable();

            $table->string('item_name')->nullable();
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->integer('quantity')->default(1)->nullable();

            $table->string('payment_status')->default('Pending')->comment('Paid', 'Part Paid', 'Pending');

            $table->decimal('deposit_amount', 10, 2)->default(0.00);
            $table->string('payment_method')->comment('Bank Transfer', 'Credit Card', 'Cash', 'Pos', 'Insurance')->nullable();

            $table->decimal('sub_total', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('grand_total', 12, 2)->default(0.00);

            $table->timestamps();
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('service_type_id')->references('id')->on('services')->onDelete('cascade');
            $table->foreign('service_unit_id')->references('id')->on('service_units')->onDelete('cascade');
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
