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
        Schema::table('lab_services', function (Blueprint $table) {
            $table->unsignedBigInteger('service_category_id')->nullable()->after('service_unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_services', function (Blueprint $table) {
            $table->dropColumn('service_category_id');
        });
    }
};
