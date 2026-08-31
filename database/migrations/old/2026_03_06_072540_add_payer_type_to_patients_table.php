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
        Schema::table('patients', function (Blueprint $table) {
            $table->string('payer_type')->nullable()->after('referral');
            $table->string('nhis_number')->nullable()->after('payer_type');
            $table->string('nhis_scheme')->nullable()->after('nhis_number');
            $table->string('hmo_name')->nullable()->after('nhis_scheme');
            $table->string('hmo_number')->nullable()->after('hmo_name');
            $table->string('principal_name')->nullable()->after('hmo_number');
            $table->string('employee_id')->nullable()->after('principal_name');
            $table->string('company_name')->nullable()->after('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('payer_type');
            $table->dropColumn('nhis_number');
            $table->dropColumn('nhis_scheme');
            $table->dropColumn('hmo_name');
            $table->dropColumn('hmo_number');
            $table->dropColumn('principal_name');
            $table->dropColumn('employee_id');
            $table->dropColumn('company_name');
        });
    }
};
