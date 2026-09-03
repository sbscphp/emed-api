<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A patient's allergies, one row each.
     *
     * They already had somewhere to live — a json column on patients holding a
     * list of bare strings, ["chills"] — but the app asks for the three things
     * that column cannot hold: what kind of allergy it is, what reaction it
     * causes, and a handle to edit or remove one of them by.
     *
     * The json column is left in place and its contents are copied across, so
     * nothing a hospital recorded is lost.
     */
    public function up(): void
    {
        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->string('name');
            $table->string('type')->nullable()->comment('Drug, Food, Environmental, Other');
            $table->text('reaction')->nullable();
            $table->string('source')->default('Patient')->comment('Patient, Hospital');
            $table->unsignedBigInteger('recorded_by')->nullable()
                ->comment('users.id on the landlord connection');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });

        $this->backfillFromPatientsColumn();
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_allergies');
    }

    /**
     * Carry the old json list over, one row per entry.
     *
     * Recorded as Hospital rather than Patient: these were typed by staff during
     * registration or a consultation, not by the patient in the app.
     */
    protected function backfillFromPatientsColumn(): void
    {
        if (!Schema::hasColumn('patients', 'allergies')) {
            return;
        }

        DB::table('patients')
            ->whereNotNull('allergies')
            ->orderBy('id')
            ->chunkById(200, function ($patients) {
                $rows = [];

                foreach ($patients as $patient) {
                    $allergies = json_decode($patient->allergies ?? '[]', true);

                    if (!is_array($allergies)) {
                        continue;
                    }

                    foreach ($allergies as $allergy) {
                        // Tolerant of both shapes: a bare string, which is what
                        // the column holds today, and an object, in case a
                        // tenant somewhere already wrote a richer one.
                        $name = is_array($allergy) ? ($allergy['name'] ?? null) : $allergy;

                        if (empty($name) || !is_string($name)) {
                            continue;
                        }

                        $rows[] = [
                            'tenant_id' => $patient->tenant_id ?? null,
                            'patient_id' => $patient->id,
                            'name' => trim($name),
                            'type' => is_array($allergy) ? ($allergy['type'] ?? null) : null,
                            'reaction' => is_array($allergy) ? ($allergy['reaction'] ?? null) : null,
                            'source' => 'Hospital',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if (!empty($rows)) {
                    DB::table('patient_allergies')->insert($rows);
                }
            });
    }
};
