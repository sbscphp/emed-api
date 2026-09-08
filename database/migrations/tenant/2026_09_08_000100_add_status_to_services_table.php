<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Services are now maintained by the hospital itself rather than seeded
     * once, so they need the active/inactive switch and the soft delete the
     * rest of the catalogue screens are built on.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'status')) {
                $table->boolean('status')->default(true)->after('price');
            }

            if (!Schema::hasColumn('services', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('services', function (Blueprint $table) {
            $table->index(['tenant_id', 'name'], 'services_tenant_id_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('services_tenant_id_name_index');
        });

        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('services', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
