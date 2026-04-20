<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('landlord')->hasColumn('tenants', 'deleted_at')) {
            Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::connection('landlord')->hasColumn('usage_fees', 'deleted_at')) {
            Schema::connection('landlord')->table('usage_fees', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::connection('landlord')->table('usage_fees', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
