<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link a patient record to the landlord user account it signs into the
     * patient mobile app with. No foreign key: users live on the landlord
     * database while patients live on the tenant one.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('patients', 'user_id')) {
            Schema::table('patients', function (Blueprint $table) {
                // Appended rather than positioned: the tenant databases do not
                // share a column order, and the oldest of them has no tenant_id
                // column to anchor to, so an after() clause fails there.
                $table->unsignedBigInteger('user_id')->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('patients', 'user_id')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }
};
