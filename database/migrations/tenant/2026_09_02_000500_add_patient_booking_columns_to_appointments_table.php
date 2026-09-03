<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns the patient mobile app adds to the appointment module.
     *
     * The schedule was built for a receptionist booking on the patient's behalf,
     * so it had nowhere to record who did the booking, which video platform a
     * tele consultation runs on, the moment the patient arrived at the hospital,
     * or why an appointment was called off.
     *
     * Columns are appended rather than positioned: tenant databases do not share
     * a column order, so an after() clause is not safe to rely on here.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'booking_source')) {
                $table->string('booking_source')->default('Hospital')->comment('Hospital, Patient');
            }

            if (!Schema::hasColumn('appointments', 'booked_by')) {
                $table->unsignedBigInteger('booked_by')->nullable()
                    ->comment('users.id on the landlord connection');
            }

            // Only ever set for a tele consultation. The link is filled in by the
            // hospital once the meeting exists, so the patient sees "Google meet"
            // from the moment they book and the join button as soon as it is
            // there.
            if (!Schema::hasColumn('appointments', 'meeting_platform')) {
                $table->string('meeting_platform')->nullable()
                    ->comment('Google Meet, Zoom, Microsoft Teams');
            }

            if (!Schema::hasColumn('appointments', 'meeting_link')) {
                $table->string('meeting_link')->nullable();
            }

            if (!Schema::hasColumn('appointments', 'checked_in_at')) {
                $table->timestamp('checked_in_at')->nullable();
            }

            if (!Schema::hasColumn('appointments', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }

            if (!Schema::hasColumn('appointments', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()
                    ->comment('users.id on the landlord connection');
            }

            if (!Schema::hasColumn('appointments', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable();
            }
        });

        // The slot picker asks "what has this doctor already got on this day?"
        // for every date the booking calendar paints, so it gets its own index.
        if (!$this->hasIndex('appointments_doctor_id_date_index')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->index(['doctor_id', 'date'], 'appointments_doctor_id_date_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('appointments_doctor_id_date_index')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropIndex('appointments_doctor_id_date_index');
            });
        }

        Schema::table('appointments', function (Blueprint $table) {
            foreach ([
                'booking_source',
                'booked_by',
                'meeting_platform',
                'meeting_link',
                'checked_in_at',
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
            ] as $column) {
                if (Schema::hasColumn('appointments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Whether the appointments table already carries the given index.
     */
    protected function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('appointments'))
            ->contains(fn($index) => ($index['name'] ?? null) === $name);
    }
};
