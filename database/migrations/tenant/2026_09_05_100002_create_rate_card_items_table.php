<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rate card catalog: a managed price list the billing manager maintains
     * and picks from when raising a manual bill.
     */
    public function up(): void
    {
        Schema::create('rate_card_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('service_unit_id')->nullable();
            $table->string('category')->nullable();
            $table->string('payer_type')->nullable()->comment('Optional payer scope e.g. Self-Pay, HMO, NHIS');
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->string('status')->default('Active')->comment('Active, Inactive');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_card_items');
    }
};
