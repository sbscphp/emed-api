<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\UserMgtHelper;
use App\Models\Laboratory;
use App\Models\LaboratoryResult;
use App\Models\PatientVisit;
use App\Repositories\Laboratory\LaboratoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Class LaboratoryService
 *
 * This class provides services related to Laboratory operations and acts as a
 * layer between the Controller and the LaboratoryRepository.
 */
class LaboratoryService
{
    /**
     * Laboratory constructor.
     *
     */
    public function __construct(LaboratoryInterface $LaboratoryInterface) {}

    /**
     * Retrieve all Laboratory.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function overview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $records = PatientVisit::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('lab_status')
            // ->where('status', PatientVisitStatusEnums::INVESTIGATION->value)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('visitno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('arrival_date', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('arrival_date', 'DESC');
            })
            ->with('patient');

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function stats($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $query = PatientVisit::query()->where('tenant_id', $tenantId);
        $labQuery = Laboratory::query()->where('tenant_id', $tenantId);
        $totalPatientsToday = (clone $query)->whereNotNull('lab_status')
            ->whereDate('created_at', now()->toDateString())->count();
        $testResultToday = (clone $labQuery)->whereDate('created_at', now()->toDateString())
            ->where('status', GeneralEnums::READY->value)->count();
        $testResultPendingToday = (clone $labQuery)->whereDate('created_at', now()->toDateString())
            ->where('status', GeneralEnums::NOT_READY->value)->count();

        return [
            'totalPatientsToday' => $totalPatientsToday,
            'testResultToday' => $testResultToday,
            'testResultPendingToday' => $testResultPendingToday
        ];
    }

    public function export($records, $format)
    {
        $exportData = $records->map(function ($visit) {
            return [
                'Patient Card'      => $visit->patient->cardno,
                'First Name'      => $visit->patient->firstname,
                'Last Name'       => $visit->patient->lastname,
                'Patient No'       => $visit->patient->patientno,
                'Visit No'       => $visit->visitno,
                'Date'      => $visit->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patients_visits.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patients_visits.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function updateResult($data, $test)
    {

        $currentUserInstance = UserMgtHelper::userInstance();
        $tenantId = $data->header('X-Tenant-ID');

        // Collect test names sent from frontend
        $incomingTests = collect($data->results)->pluck('test')->toArray();

        // Delete results that are no longer present in the request
        LaboratoryResult::where('patient_visit_lab_id', $test->id)
            ->whereNotIn('test', $incomingTests)
            ->delete();

        // Create or update results for the current set
        foreach ($data->results as $item) {
            LaboratoryResult::updateOrCreate(
                [
                    'patient_visit_lab_id' => $test->id,
                    'test' => $item['test'],
                ],
                [
                    'tenant_id'        => $tenantId,
                    'visit_id'         => $test->visit_id,
                    'result'           => $item['result'],
                    'reference_range'  => $item['reference_range'],
                    'status'           => 'Ready',
                ]
            );
        }

        $test->update([
            'specimen_type' => $data->specimen_type,
            'notes'         => $data->notes,
            'user_id'    => $currentUserInstance->id,
            // 'requested_by'  => $data->requested_by,
            'status'   => 'Ready'
        ]);

        $visit = PatientVisit::find($test->visit_id);
        $totalLabRequests = Laboratory::where('visit_id', $visit->id)->count();
        $completedLabRequests = LaboratoryResult::whereIn(
            'patient_visit_lab_id',
            Laboratory::where('visit_id', $visit->id)->pluck('id')
        )->count();
        if ($totalLabRequests > 0 && $totalLabRequests === $completedLabRequests) {
            $visit->update([
                'lab_status' => GeneralEnums::COMPLETED->value,
            ]);
        }

        return $test->refresh()->load('results');
    }

    public function updateTest($data, $test)
    {

        $test->update([
            'status'   => $data->status
        ]);

        return $test->refresh();
    }
}
