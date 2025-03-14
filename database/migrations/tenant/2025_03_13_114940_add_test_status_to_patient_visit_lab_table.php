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
        Schema::table('patient_visit_lab', function (Blueprint $table) {
            $table->enum('test_status',['pending','completed'])->default('pending');
            $table->enum('payment_status',['pending','paid'])->default('pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_visit_lab', function (Blueprint $table) {
            $table->dropColumn('test_status');
            $table->dropColumn('payment_status');
        });
    }
};
