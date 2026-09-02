<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns the patient mobile app needs on the landlord users table.
     *
     * `must_change_password` carries the "change the password we mailed you"
     * prompt through to the first login, and `biometric_enabled` remembers the
     * face ID / fingerprint choice made right after that first login.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('is_completed');
            }

            if (!Schema::hasColumn('users', 'biometric_enabled')) {
                $table->boolean('biometric_enabled')->default(false)->after('must_change_password');
            }

            // Written by the login endpoints already, but never declared.
            if (!Schema::hasColumn('users', 'last_login')) {
                $table->timestamp('last_login')->nullable()->after('biometric_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['must_change_password', 'biometric_enabled', 'last_login'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
