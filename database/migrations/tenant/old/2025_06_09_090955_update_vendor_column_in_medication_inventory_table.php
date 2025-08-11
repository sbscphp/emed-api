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
            if (Schema::hasColumn('medication_inventory', 'vendor')) {
                $table->dropColumn('vendor');
            }

            if (!Schema::hasColumn('medication_inventory', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('id');
                $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medication_inventory', function (Blueprint $table) {
            if (Schema::hasColumn('medication_inventory', 'vendor_id')) {
                $table->dropForeign(['vendor_id']);
                $table->dropColumn('vendor_id');
            }

            if (!Schema::hasColumn('medication_inventory', 'vendor')) {
                $table->string('vendor')->nullable()->after('id');
            }
        });
    }
};
