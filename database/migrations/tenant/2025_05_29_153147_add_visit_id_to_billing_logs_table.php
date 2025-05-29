<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('billing_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('visit_id')->nullable()->after('patient_id');
        });
    }

    public function down()
    {
        Schema::table('billing_logs', function (Blueprint $table) {
            $table->dropColumn('visit_id');
        });
    }
};
