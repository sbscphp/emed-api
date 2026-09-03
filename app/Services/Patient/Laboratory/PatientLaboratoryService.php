<?php

namespace App\Services\Patient\Laboratory;

use App\Models\Laboratory;
use App\Services\Patient\Diagnostics\DiagnosticResultService;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PatientLaboratoryService
 *
 * The laboratory module of the patient mobile app: the tests the hospital ran,
 * split into the ones whose results are available and the ones still being
 * worked on, one result opened up, its report as a PDF, and the previous
 * reports of the same test.
 *
 * Read only. Laboratory results are entered by the lab; a patient reads them.
 *
 * @see \App\Services\Patient\Diagnostics\DiagnosticResultService for the parts
 *      shared with radiology.
 */
class PatientLaboratoryService extends DiagnosticResultService
{
    /**
     * Laboratory orders belonging to the signed in patient.
     *
     * The hospital half of the scoping is the database rather than a column:
     * PatientContextService has made the tenant current, and patient_visit_lab
     * lives in that tenant's own database. Its tenant_id column is nullable and
     * empty on older rows, so filtering on it would quietly hide results.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function newQuery()
    {
        return Laboratory::query()->where('patient_id', $this->patientId());
    }

    /**
     * @return array<int, string>
     */
    protected function relations(): array
    {
        return ['results', 'orderedBy', 'patient'];
    }

    /**
     * @param  \App\Models\Laboratory  $order
     */
    protected function renderReport(Model $order): string
    {
        return $this->reports->laboratory($order, $this->context->tenant());
    }

    protected function notFoundMessage(): string
    {
        return 'We could not find that laboratory result.';
    }
}
