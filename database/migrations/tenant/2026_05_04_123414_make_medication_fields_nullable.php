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
        Schema::table('medications', function (Blueprint $table) {
            $table->string('generic_name')->nullable()->change();
            $table->string('brand_name')->nullable()->change();
            $table->string('medicine_name')->nullable()->change();
            $table->string('medicine_type')->nullable()->change();
            $table->string('manufacturer')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->string('generic_name')->nullable(false)->change();
            $table->string('brand_name')->nullable(false)->change();
            $table->string('medicine_name')->nullable(false)->change();
            $table->string('medicine_type')->nullable(false)->change();
            $table->string('manufacturer')->nullable(false)->change();
        });
    }
};
