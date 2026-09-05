<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The payment ledger. Every attempt to pay a billing (manual transfer/cash or
     * gateway card) is recorded here. The unique `reference` is the idempotency key
     * so a Paystack webhook and a verify-on-return can never double-credit a bill.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('billing_id');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->string('currency')->default('NGN');
            $table->string('channel')->comment('card, transfer, cash, pos, insurance');
            $table->string('gateway')->default('manual')->comment('paystack, manual');
            $table->string('reference')->unique();
            $table->string('gateway_reference')->nullable();
            $table->string('status')->default('pending')->comment('pending, success, failed');
            $table->timestamp('paid_at')->nullable();
            $table->string('initiated_by')->default('staff')->comment('patient, staff');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('billing_id')->references('id')->on('billing_logs')->onDelete('cascade');
            $table->index(['billing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
