<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguish manually-raised bills from clinical ones and carry a free-text note.
     * `visit_id` is already nullable, so standalone (patient-only) manual bills need no
     * schema change beyond this — only the request validation is relaxed.
     */
    public function up(): void
    {
        Schema::table('billing_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_logs', 'type')) {
                $table->string('type')->default('clinical')->after('invoice_number')
                    ->comment('clinical, manual');
            }
            if (!Schema::hasColumn('billing_logs', 'notes')) {
                $table->text('notes')->nullable()->after('payment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_logs', function (Blueprint $table) {
            $table->dropColumn(['type', 'notes']);
        });
    }
};
