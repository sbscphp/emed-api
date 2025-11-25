<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\UserMgtHelper;
use App\Models\Laboratory;
use App\Models\LaboratoryResult;
use App\Models\PatientVisit;
use App\Models\Radiology;
use App\Models\RadiologyResult;
use App\Repositories\Laboratory\LaboratoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Class RadiologyService
 *
 * This class provides services related to Laboratory operations and acts as a
 * layer between the Controller and the LaboratoryRepository.
 */
class RadiologyService
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
            ->whereNotNull('rad_status')
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
        $totalPatientsToday = (clone $query)->whereNotNull('rad_status')
            ->whereDate('created_at', now()->toDateString())->count();
        $testResultToday = Radiology::where('tenant_id', $tenantId)->whereDate('created_at', now()->toDateString())
            ->where('status', GeneralEnums::READY->value)->count();
        $testResultPendingToday = Radiology::where('tenant_id', $tenantId)->whereDate('created_at', now()->toDateString())
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
        $userId = $currentUserInstance->id;
        $tenantId = $data->header('X-Tenant-ID');;

        $resultImage = null;

        if (!empty($data->result_img)) {
            if (filter_var($data->result_img, FILTER_VALIDATE_URL)) {
                // It’s already a URL (don’t re-upload)
                $resultImage = $data->result_img;
            } else {
                // Must be base64
                $resultImage = FileUploadHelper::singleStringFileUpload($data->result_img, "radiology_results");
            }
        }

        // Update existing or create new radiology result using radiology_id as unique key
        $record = RadiologyResult::updateOrCreate(
            [
                'radiology_id' => $test->id, // unique key
            ],
            [
                'tenant_id'      => $tenantId,
                'user_id'            => $userId,
                'patient_id'         => $data->patient_id,
                'examination_type'   => $data->examination_type,
                'clinical_indication' => $data->clinical_indication,
                'technique'          => $data->technique,
                'findings'           => $data->findings,
                'result_img'         => $resultImage,
                'status'         => 'Ready',
            ]
        );

        $test->update([
            'user_id'    => $currentUserInstance->id,
            'status'   => 'Ready'
        ]);

        $visit = PatientVisit::find($test->visit_id);
        $totalTests = Radiology::where('visit_id', $visit->id)->count();
        $completedTests = RadiologyResult::whereIn(
            'radiology_id',
            Radiology::where('visit_id', $visit->id)->pluck('id')
        )->count();

        if ($totalTests > 0 && $totalTests === $completedTests) {
            $visit->update([
                'rad_status' => GeneralEnums::COMPLETED->value,
            ]);
        }

        return $test->refresh()->load('result');
    }

    public function updateTest($data, $test)
    {

        $test->update([
            'status'   => $data->status
        ]);

        return $test->refresh();
    }
}
