<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give patients the columns they need to authenticate in the mobile app.
     * `email` already exists on the patients table.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'password')) {
                $table->string('password')->nullable()->after('email');
            }
            if (!Schema::hasColumn('patients', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('password');
            }
            if (!Schema::hasColumn('patients', 'remember_token')) {
                $table->string('remember_token', 100)->nullable()->after('email_verified_at');
            }
            if (!Schema::hasColumn('patients', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('remember_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['password', 'email_verified_at', 'remember_token', 'last_login_at']);
        });
    }
};
