<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('client_usage_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('usage_fee_id');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->date('billing_month')->comment('First day of the billed month, e.g. 2026-04-01');
            $table->unsignedInteger('total_visits')->default(0)->comment('Visit count used for billing (all visits or distinct patients)');
            $table->decimal('fee_per_visit', 10, 2)->default(0.00)->comment('Snapshot of usage fee amount at the time of charge generation');
            $table->decimal('total_amount', 10, 2)->default(0.00)->comment('total_visits * fee_per_visit');
            $table->string('status')->default('Pending')->comment('Pending, Paid');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('usage_fee_id')->references('id')->on('usage_fees')->onDelete('restrict');

            // Ensure only one charge record per tenant per billing month
            $table->unique(['tenant_id', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('client_usage_charges');
    }
};
