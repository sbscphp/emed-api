<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The columns this migration guarantees on admitted_patients, each keyed by
     * name so the table can be brought up to date whatever state it is in.
     *
     * No column is positioned with after(): tenant databases have drifted from
     * one another and an anchor column is not guaranteed to exist. Ordering is
     * cosmetic, so the columns simply append.
     *
     * @var array<string, callable>
     */
    protected function columns(): array
    {
        return [
            // Base columns an older tenant schema may be missing entirely.
            'date_discharged' => fn(Blueprint $table) => $table->date('date_discharged')->nullable(),

            // The admission itself.
            'admission_no' => fn(Blueprint $table) => $table->string('admission_no')->nullable()->index(),
            'admission_type' => fn(Blueprint $table) => $table->string('admission_type')->nullable()->index()
                ->comment('Maternity, Surgery, Observation, Day case, Medical, Emergency, Other'),
            'department_id' => fn(Blueprint $table) => $table->unsignedBigInteger('department_id')->nullable()->index(),
            'doctor_id' => fn(Blueprint $table) => $table->unsignedBigInteger('doctor_id')->nullable()->index()
                ->comment('users.id on the landlord connection'),
            'bed_id' => fn(Blueprint $table) => $table->unsignedBigInteger('bed_id')->nullable()->index(),
            'referred_by' => fn(Blueprint $table) => $table->string('referred_by')->nullable(),
            'reason' => fn(Blueprint $table) => $table->text('reason')->nullable(),
            'notes' => fn(Blueprint $table) => $table->text('notes')->nullable(),

            // Timing.
            'admission_time' => fn(Blueprint $table) => $table->time('admission_time')->nullable(),
            'expected_admission_date' => fn(Blueprint $table) => $table->date('expected_admission_date')->nullable(),
            'expected_admission_time' => fn(Blueprint $table) => $table->time('expected_admission_time')->nullable(),
            'discharge_time' => fn(Blueprint $table) => $table->time('discharge_time')->nullable(),
            'discharge_notes' => fn(Blueprint $table) => $table->text('discharge_notes')->nullable(),

            // Money.
            'payer_type' => fn(Blueprint $table) => $table->string('payer_type')->nullable(),
            'deposit_amount' => fn(Blueprint $table) => $table->decimal('deposit_amount', 12, 2)->default(0.00),
            'payment_status' => fn(Blueprint $table) => $table->string('payment_status')->default('Pending')
                ->comment('Paid, Part Paid, Pending'),
            'billing_id' => fn(Blueprint $table) => $table->unsignedBigInteger('billing_id')->nullable()->index(),

            // Cancellation and authorship.
            'cancelled_by' => fn(Blueprint $table) => $table->unsignedBigInteger('cancelled_by')->nullable(),
            'cancelled_at' => fn(Blueprint $table) => $table->dateTime('cancelled_at')->nullable(),
            'cancellation_reason' => fn(Blueprint $table) => $table->text('cancellation_reason')->nullable(),
            'created_by' => fn(Blueprint $table) => $table->unsignedBigInteger('created_by')->nullable(),
        ];
    }

    /**
     * Run the migrations.
     *
     * Widens admitted_patients so it can carry a full admission (new, scheduled
     * and emergency) instead of only a ward assignment.
     */
    public function up(): void
    {
        if (!Schema::hasTable('admitted_patients')) {
            return;
        }

        $existing = Schema::getColumnListing('admitted_patients');

        Schema::table('admitted_patients', function (Blueprint $table) use ($existing) {
            foreach ($this->columns() as $name => $definition) {
                if (!in_array($name, $existing, true)) {
                    $definition($table);
                }
            }
        });

        if (in_array('status', $existing, true)) {
            Schema::table('admitted_patients', function (Blueprint $table) {
                $table->string('status')->default('Pending')
                    ->comment('Pending, Scheduled, Admitted, Discharged, Cancelled')
                    ->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * date_discharged predates this migration on most tenants, so it is left in
     * place rather than dropped.
     */
    public function down(): void
    {
        if (!Schema::hasTable('admitted_patients')) {
            return;
        }

        $existing = Schema::getColumnListing('admitted_patients');

        Schema::table('admitted_patients', function (Blueprint $table) use ($existing) {
            foreach (array_keys($this->columns()) as $name) {
                if ($name !== 'date_discharged' && in_array($name, $existing, true)) {
                    $table->dropColumn($name);
                }
            }
        });
    }
};
