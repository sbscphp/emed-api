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
        Schema::table('lab_test_results', function (Blueprint $table) {
            if (!Schema::hasColumn('lab_test_results', 'lab_parameter_id')) {
                $table->unsignedBigInteger('lab_parameter_id')->nullable()->after('visit_id');
            }

            if (!Schema::hasColumn('lab_test_results', 'unit')) {
                $table->string('unit')->nullable()->after('result');
            }

            if (!Schema::hasColumn('lab_test_results', 'flag')) {
                $table->string('flag')->nullable()->after('reference_range');
            }

            if (!Schema::hasColumn('lab_test_results', 'display_order')) {
                $table->unsignedInteger('display_order')->default(0)->after('flag');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_test_results', function (Blueprint $table) {
            if (Schema::hasColumn('lab_test_results', 'display_order')) {
                $table->dropColumn('display_order');
            }

            if (Schema::hasColumn('lab_test_results', 'flag')) {
                $table->dropColumn('flag');
            }

            if (Schema::hasColumn('lab_test_results', 'unit')) {
                $table->dropColumn('unit');
            }

            if (Schema::hasColumn('lab_test_results', 'lab_parameter_id')) {
                $table->dropColumn('lab_parameter_id');
            }
        });
    }
};
