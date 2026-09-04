<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the patient app's notification screen needs and the table did not
     * hold.
     *
     * The rows written so far were for hospital staff, where a title and a
     * message are the whole of it. A patient's notification has to open
     * something — a released result, an invoice, an appointment — so it needs to
     * say what kind of thing happened (`type`, which also picks the icon) and
     * carry the id of the record to open (`data`).
     *
     * `audience` keeps the two apart. Without it the patient app would list
     * every ward and billing notice the hospital wrote for itself.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type')->nullable()->after('message')
                ->comment('lab_result_ready, payment_successful, appointment_reminder, ...');
            $table->string('audience')->default('Staff')->after('role')
                ->comment('Staff, Patient');
            $table->json('data')->nullable()->after('audience')
                ->comment('What the app needs to open the record this is about');
            $table->unsignedBigInteger('patient_id')->nullable()->after('user_id')
                ->comment('patients.id, so a patient notification survives an account being relinked');

            $table->index(['audience', 'user_id']);
        });

        // Everything written before this migration was for hospital staff.
        DB::table('notifications')->update(['audience' => 'Staff']);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['audience', 'user_id']);
            $table->dropColumn(['type', 'audience', 'data', 'patient_id']);
        });
    }
};
