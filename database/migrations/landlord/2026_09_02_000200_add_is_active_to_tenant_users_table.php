<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore the is_active column create_tenant_users_table declares.
     *
     * The live table was built from an earlier shape of that migration, which
     * is already recorded as run, so the column never arrived. Every write path
     * that attaches a user to a hospital sets it — staff creation, staff update,
     * hospital registration and patient onboarding — and each of them dies on
     * "Unknown column 'is_active' in 'field list'" until this lands.
     */
    public function up(): void
    {
        if (Schema::hasColumn('tenant_users', 'is_active')) {
            return;
        }

        Schema::table('tenant_users', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('date_of_birth');
        });

        // Existing memberships predate the column. Nothing authorises on it yet
        // (login reads status), so status is the honest source for a backfill,
        // and it keeps the two from contradicting each other from day one.
        DB::table('tenant_users')->where('status', 'Active')->update(['is_active' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tenant_users', 'is_active')) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
