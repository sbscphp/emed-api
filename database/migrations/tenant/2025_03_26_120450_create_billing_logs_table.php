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

            $table->unsignedBigInteger('patient_id');
            $table->string('patient_name');

            $table->date('billing_date');

            $table->unsignedBigInteger('service_type_id');
            $table->unsignedBigInteger('service_unit_id');

            $table->string('item_name');
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity')->default(1);

            $table->enum('payment_status', ['paid', 'part_paid', 'pending'])->default('pending');

            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->enum('payment_method', ['bank_transfer', 'credit_card', 'cash', 'pos', 'insurance'])->nullable();

            $table->decimal('sub_total', 12, 2);
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('grand_total', 12, 2);

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
