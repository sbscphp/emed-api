<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The short code the patient app prints as "Hospital ID" on the Linked
     * Hospitals screen.
     *
     * It could not be derived at read time. `registration_number` is the
     * hospital's own CAC or licence number, free text, duplicated across
     * hospitals in the data as it stands, and not something to show a patient;
     * the uuid is unreadable; and anything computed from the name would move the
     * moment a hospital was renamed. A patient quotes this code at a front desk,
     * so it has to be short, unique and fixed once issued.
     *
     * Shape is the hospital's initials and six digits — LCH-677888 — with the
     * digits derived from the uuid so the same hospital gets the same code
     * whichever environment this runs in.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('hospital_code')->nullable()->unique()->after('uuid');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['hospital_code']);
            $table->dropColumn('hospital_code');
        });
    }

    /**
     * Issue a code to every hospital that already exists.
     */
    protected function backfill(): void
    {
        $taken = [];

        DB::table('tenants')->orderBy('id')->each(function ($tenant) use (&$taken) {
            $code = $this->codeFor($tenant, $taken);
            $taken[$code] = true;

            DB::table('tenants')->where('id', $tenant->id)->update(['hospital_code' => $code]);
        });
    }

    /**
     * Initials plus six stable digits, nudged if two hospitals collide.
     */
    protected function codeFor($tenant, array $taken): string
    {
        $words = preg_split('/[^A-Za-z]+/', (string) $tenant->name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $initials = collect($words)
            ->take(3)
            ->map(fn($word) => strtoupper($word[0]))
            ->implode('');

        // A hospital named entirely in digits or punctuation still needs a code.
        $initials = Str::padRight($initials, 2, 'H');

        // Digits from the uuid rather than the auto increment id, so a hospital
        // restored into a different database keeps the code patients know it by.
        $digits = substr((string) abs(crc32((string) ($tenant->uuid ?: $tenant->id))), 0, 6);
        $digits = str_pad($digits, 6, '0', STR_PAD_LEFT);

        $code = "{$initials}-{$digits}";
        $suffix = 1;

        while (isset($taken[$code])) {
            $code = "{$initials}-{$digits}" . $suffix++;
        }

        return $code;
    }
};
