<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('lab_parameters', 'lab_test_id')) {
            Schema::table('lab_parameters', function (Blueprint $table) {
                $table->foreignId('lab_test_id')
                    ->nullable()
                    ->after('service_category_id')
                    ->constrained('lab_services')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lab_parameters', 'lab_test_id')) {
            Schema::table('lab_parameters', function (Blueprint $table) {
                $table->dropConstrainedForeignId('lab_test_id');
            });
        }
    }
};
