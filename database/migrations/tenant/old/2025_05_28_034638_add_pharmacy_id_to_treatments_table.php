<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('tenant')->table('patient_visit_treatment', function (Blueprint $table) {
            // $table->unsignedBigInteger('pharmacy_id')->nullable()->after('consultation_id');

            // $table->foreign('pharmacy_id')
            //     ->references('id')
            //     ->on('pharmacies')
            //     ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::connection('tenant')->table('patient_visit_treatment', function (Blueprint $table) {
            // $table->dropForeign(['pharmacy_id']);
            // $table->dropColumn('pharmacy_id');
        });
    }
};
