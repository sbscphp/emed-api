<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why an appointment was moved.
     *
     * Calling an appointment off already records its reason, but moving one did
     * not: the reschedule sheet in the patient app asks the patient why, and the
     * hospital reads it next to the new date. It is kept apart from the visit's
     * own `reason` column, which answers "what are you coming in for?" and must
     * survive a reschedule untouched.
     *
     * Appended rather than positioned: tenant databases do not share a column
     * order, so an after() clause is not safe to rely on here.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'reschedule_reason')) {
                $table->text('reschedule_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'reschedule_reason')) {
                $table->dropColumn('reschedule_reason');
            }
        });
    }
};
