<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs') && !Schema::hasColumn('audit_logs', 'action')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->string('action')->nullable()->after('action_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'action')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropColumn('action');
            });
        }
    }
};

