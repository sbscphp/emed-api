<?php

namespace App\Services\Patient\Radiology;

use App\Models\Radiology;
use App\Models\RadiologyService;
use App\Services\Patient\Diagnostics\DiagnosticResultService;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PatientRadiologyService
 *
 * The radiology module of the patient mobile app: the scans and images the
 * hospital ordered, split into the ones whose reports have been released and
 * the ones still being read, one report opened up, that report as a PDF, and
 * the previous reports of the same examination.
 *
 * Read only. Radiology reports are written by the radiologist; a patient reads
 * them.
 *
 * @see \App\Services\Patient\Diagnostics\DiagnosticResultService for the parts
 *      shared with laboratory.
 */
class PatientRadiologyService extends DiagnosticResultService
{
    /**
     * Radiology orders belonging to the signed in patient.
     *
     * Scoped by patient inside the tenant's own database, for the same reason
     * the laboratory module is: the tenant_id column on this table is nullable
     * and empty on older rows.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function newQuery()
    {
        return Radiology::query()->where('patient_id', $this->patientId());
    }

    /**
     * @return array<int, string>
     */
    protected function relations(): array
    {
        return ['results', 'orderedBy', 'patient'];
    }

    /**
     * @param  \App\Models\Radiology  $order
     */
    protected function renderReport(Model $order): string
    {
        return $this->reports->radiology($order, $this->context->tenant());
    }

    protected function notFoundMessage(): string
    {
        return 'We could not find that radiology report.';
    }

    /**
     * The hospital's radiology service catalogue, which
     * patient_visit_radiology.test_id is an id in.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function testCatalogue()
    {
        return RadiologyService::query();
    }

    protected function testNotFoundMessage(): string
    {
        return 'We could not find that radiology examination.';
    }
}
