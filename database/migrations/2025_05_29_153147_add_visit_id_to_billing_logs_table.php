<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('billing_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('visit_id')->after('patient_id');
            // $table->foreign('visit_id')->references('id')->on('patient_visits')->onDelete('cascade');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_logs', function (Blueprint $table) {
            //
        });
    }
};
