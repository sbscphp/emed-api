<?php

namespace App\Services\Patient\Records;

use App\Models\AdmittedPatient;
use App\Models\Laboratory;
use App\Models\Radiology;
use App\Services\Patient\PatientContextService;
use App\Services\Patient\Vitals\PatientVitalsService;
use Carbon\Carbon;

/**
 * Class PatientRecordService
 *
 * The health record section of the patient mobile app: the four tiles on the
 * home screen and the "My Record" page behind their View All.
 *
 * This module owns the counting only. Each record type opens into a module of
 * its own — vitals already, laboratory, radiology and admissions as their
 * screens arrive — and the tile here is the doorway to it, so the tile carries
 * the route key the app navigates on rather than the records themselves.
 */
class PatientRecordService
{
    public function __construct(
        protected PatientContextService $context,
        protected PatientVitalsService $vitals,
    ) {}

    /**
     * The four tiles of the home screen's "Your Health Record" strip.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tiles(): array
    {
        // The hospital half of the scoping is the database rather than a column:
        // PatientContextService has made the tenant current, and all three of
        // these tables live in that tenant's own database. Their tenant_id
        // columns are deliberately not filtered on as well, because they are
        // nullable and empty on rows written before they were added, which would
        // quietly hide a patient's older records from them.
        $patientId = $this->context->patient()->id;
        $newSince = $this->newSince();

        // Only results the hospital has released. A test that has been ordered
        // but not reported is not something the patient can open, so counting it
        // would put a number on the tile that the list behind it cannot show.
        $lab = Laboratory::query()->where('patient_id', $patientId)->where('status', 'Ready');
        $radiology = Radiology::query()->where('patient_id', $patientId)->where('status', 'Ready');
        $admissions = AdmittedPatient::query()->where('patient_id', $patientId);
        $vitals = $this->vitals->summary();

        return [
            [
                'key' => 'lab_result',
                'name' => 'Lab Result',
                'module' => 'laboratory',
                'total' => (clone $lab)->count(),
                'badge' => $this->badge((clone $lab)->where('created_at', '>=', $newSince)->count(), 'new'),
            ],
            [
                'key' => 'radiology',
                'name' => 'Radiology',
                'module' => 'radiology',
                'total' => (clone $radiology)->count(),
                'badge' => $this->badge((clone $radiology)->where('created_at', '>=', $newSince)->count(), 'new'),
            ],
            [
                // Admissions count what is happening now rather than what is
                // recent: "+1 Active" is a patient currently on a ward.
                'key' => 'admission',
                'name' => 'Admission',
                'module' => 'admission',
                'total' => (clone $admissions)->count(),
                'badge' => $this->badge((clone $admissions)->where('status', 'Admitted')->count(), 'Active'),
            ],
            [
                'key' => 'vitals',
                'name' => 'Vitals',
                'module' => 'vitals',
                'total' => $vitals['total'],
                'badge' => $this->badge($vitals['new'], 'new'),
            ],
        ];
    }

    /**
     * The "My Record" page, which is the same counts grouped the way that
     * screen headings them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sections(): array
    {
        $tiles = collect($this->tiles())->keyBy('key');

        return [
            [
                'name' => 'Health Measurement',
                'records' => [
                    $this->entry($tiles->get('vitals'), 'Vitals', 'Track your health measurements'),
                ],
            ],
            [
                'name' => 'Clinical Reports',
                'records' => [
                    $this->entry($tiles->get('lab_result'), 'Lab Result', 'View your test results and reports'),
                    $this->entry($tiles->get('radiology'), 'Radiology', 'View your imaging and scan results'),
                ],
            ],
            [
                'name' => 'Medical History',
                'records' => [
                    $this->entry($tiles->get('admission'), 'Admissions', 'View previous admission history'),
                ],
            ],
        ];
    }

    /**
     * Shape one row of the "My Record" page from its tile.
     *
     * @param  array<string, mixed>|null  $tile
     * @return array<string, mixed>
     */
    protected function entry(?array $tile, string $name, string $description): array
    {
        return [
            'key' => $tile['key'] ?? null,
            'name' => $name,
            'description' => $description,
            'module' => $tile['module'] ?? null,
            'total' => $tile['total'] ?? 0,
            'badge' => $tile['badge'] ?? null,
        ];
    }

    /**
     * The small caption under a tile, or nothing at all when there is no news.
     *
     * @return array{count:int, label:string}|null
     */
    protected function badge(int $count, string $label): ?array
    {
        if ($count < 1) {
            return null;
        }

        return ['count' => $count, 'label' => $label];
    }

    /**
     * The cut off a record has to fall after to count as new.
     */
    protected function newSince(): Carbon
    {
        return Carbon::now()->subDays((int) config('patient_app.records.new_within_days'));
    }
}
