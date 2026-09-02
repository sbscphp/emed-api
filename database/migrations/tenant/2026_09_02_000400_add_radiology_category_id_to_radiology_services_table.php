<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable so the services seeded before categories existed stay valid;
     * RadiologyTestSeeder backfills them on its next run.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('radiology_services', 'radiology_category_id')) {
            Schema::table('radiology_services', function (Blueprint $table) {
                $table->unsignedBigInteger('radiology_category_id')->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('radiology_services', 'radiology_category_id')) {
            Schema::table('radiology_services', function (Blueprint $table) {
                $table->dropColumn('radiology_category_id');
            });
        }
    }
};
