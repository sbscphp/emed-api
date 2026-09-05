<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional doctor chosen at patient registration; used as the default doctor
     * when a visit is initiated without an explicit one.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'preferred_doctor_id')) {
                $table->unsignedBigInteger('preferred_doctor_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('preferred_doctor_id');
        });
    }
};
