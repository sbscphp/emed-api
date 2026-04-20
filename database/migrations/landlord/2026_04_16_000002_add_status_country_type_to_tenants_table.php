<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            if (!Schema::connection('landlord')->hasColumn('tenants', 'country')) {
                $table->string('country')->nullable()->after('name');
            }
            if (!Schema::connection('landlord')->hasColumn('tenants', 'hospital_type')) {
                $table->string('hospital_type')->nullable()->after('address');
            }
            if (!Schema::connection('landlord')->hasColumn('tenants', 'status')) {
                $table->string('status')->default('Active')->after('license');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            if (Schema::connection('landlord')->hasColumn('tenants', 'country')) {
                $table->dropColumn('country');
            }
            if (Schema::connection('landlord')->hasColumn('tenants', 'hospital_type')) {
                $table->dropColumn('hospital_type');
            }
            if (Schema::connection('landlord')->hasColumn('tenants', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
