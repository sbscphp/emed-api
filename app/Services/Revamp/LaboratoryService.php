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

        $records = PatientVisit::query()
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
        $query = PatientVisit::query();
        $totalPatientsToday = (clone $query)->where('status', PatientVisitStatusEnums::INVESTIGATION->value)
            ->whereDate('created_at', now()->toDateString())->count();
        $testResultToday = Laboratory::whereDate('created_at', now()->toDateString())
            ->where('status', GeneralEnums::READY->value)->count();
        $testResultPendingToday = Laboratory::whereDate('created_at', now()->toDateString())
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
        $tenant = $currentUserInstance->tenant->domain;

        // Create lab result
        foreach ($data->results as $item) {
            $record = LaboratoryResult::updateOrCreate(
                [
                    'patient_visit_lab_id' => $test->id, // Unique match key
                    'test' => $item['test'],
                ],
                [
                    'tenant_domain'    => $tenant,
                    'visit_id'          => $test->visit_id,
                    'result'           => $item['result'],
                    'reference_range'  => $item['reference_range'],
                    'status'           => 'Ready'
                ]
            );
        }

        $test->update([
            'specimen_type' => $data->specimen_type,
            'notes'         => $data->notes,
            // 'requested_by'  => $data->requested_by,
            // 'test_status'   => 'completed'
        ]);

        return $record;
    }

    public function updateTest($data, $test)
    {

        $test->update([
            'status'   => $data->status
        ]);

        return $test->refresh();
    }
}
