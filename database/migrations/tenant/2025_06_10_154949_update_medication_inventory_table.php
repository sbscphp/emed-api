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
        Schema::table('medication_inventory', function (Blueprint $table) {
            $table->unsignedBigInteger('medication_id')->nullable()->change();
            $table->unsignedBigInteger('pharmacy_id')->nullable()->change();
            $table->string('shipment_no')->nullable()->change();
            $table->unsignedInteger('received_qty')->nullable()->change();

            $table->string('active_ingredient')->nullable()->after('vendor');
            $table->string('brand_name')->nullable()->after('active_ingredient');
            $table->decimal('price', 10, 2)->nullable()->after('brand_name');
        });
    }

    public function down(): void
    {
        Schema::table('medication_inventory', function (Blueprint $table) {
            $table->unsignedBigInteger('medication_id')->nullable(false)->change();
            $table->unsignedBigInteger('pharmacy_id')->nullable(false)->change();
            $table->string('shipment_no')->nullable(false)->change();
            $table->unsignedInteger('received_qty')->nullable(false)->change();

            $table->dropColumn(['active_ingredient', 'brand_name', 'price']);
        });
    }
};
