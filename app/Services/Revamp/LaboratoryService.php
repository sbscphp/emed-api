<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\UserMgtHelper;
use App\Models\LabService;
use App\Models\Laboratory;
use App\Models\LaboratoryResult;
use App\Models\PatientVisit;
use App\Repositories\Laboratory\LaboratoryInterface;
use App\Services\Patient\Notification\PatientNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class LaboratoryService
 *
 * This class provides services related to Laboratory operations and acts as a
 * layer between the Controller and the LaboratoryRepository.
 */
class LaboratoryService
{
    protected LabParameterService $labParameterService;

    /**
     * Laboratory constructor.
     *
     */
    /**
     * The patient app's notification writer.
     *
     * Releasing a result is the moment the patient can see it, so it is also the
     * moment they are told. Held here rather than resolved at the call site so
     * the dependency is visible on the class.
     */
    protected PatientNotificationService $patientNotifications;

    public function __construct(
        LaboratoryInterface $LaboratoryInterface,
        LabParameterService $labParameterService,
        PatientNotificationService $patientNotifications
    ) {
        $this->labParameterService = $labParameterService;
        $this->patientNotifications = $patientNotifications;
    }

    public function supportsLabTestParameters(): bool
    {
        return $this->labParameterService->supportsLabTestParameters();
    }

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
            ->when(!empty($request['lab_status']), function ($query) use ($request) {
                $query->where('lab_status', $request['lab_status']);
            })
            ->when(!empty($request['patient_status']), function ($query) use ($request) {
                $query->whereRelation('patient', 'status', $request['patient_status']);
            })
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
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
        $testResultToday = (clone $query)->whereDate('created_at', now()->toDateString())
            ->where('lab_status', GeneralEnums::COMPLETED->value)->count();
        $testResultPendingToday = (clone $query)->whereDate('created_at', now()->toDateString())
            ->where('lab_status', GeneralEnums::PENDING->value)->count();

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
                'Visit Status'       => $visit->status,
                'Lab Status'       => $visit->lab_status,
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
        $relations = [
            'serviceCategory.labParameters' => function ($query) {
                $query->where('status', true)
                    ->orderBy('display_order')
                    ->orderBy('id');
            }
        ];

        if ($this->labParameterService->supportsLabTestParameters()) {
            $relations['labParameters'] = function ($query) {
                $query->where('status', true)
                    ->orderBy('display_order')
                    ->orderBy('id');
            };
        }

        $service = $test->testService()->with($relations)->first();
        $effectiveParameters = $this->labParameterService->getEffectiveParametersForLabTest($service);

        $results = collect($data->results ?? []);
        $parameterIds = $results->pluck('lab_parameter_id')->filter()->values()->all();
        $incomingTests = $results->pluck('test')->filter()->values()->all();

        if ($service && !empty($parameterIds)) {
            $allowedParameterIds = $effectiveParameters->pluck('id')->all();
            $invalidParameterIds = array_diff($parameterIds, $allowedParameterIds);

            if (!empty($invalidParameterIds)) {
                throw new \InvalidArgumentException('One or more submitted parameters do not belong to the selected lab test.');
            }
        }

        if (!empty($parameterIds)) {
            LaboratoryResult::where('patient_visit_lab_id', $test->id)
                ->whereNotIn('lab_parameter_id', $parameterIds)
                ->delete();
        } elseif (!empty($incomingTests)) {
            LaboratoryResult::where('patient_visit_lab_id', $test->id)
                ->whereNotIn('test', $incomingTests)
                ->delete();
        } else {
            LaboratoryResult::where('patient_visit_lab_id', $test->id)->delete();
        }

        foreach ($results as $index => $item) {
            $parameter = null;
            if (!empty($item['lab_parameter_id'])) {
                $parameter = $effectiveParameters
                    ->firstWhere('id', (int) $item['lab_parameter_id']);
            }

            $parameterName = $item['test'] ?? $parameter?->name;
            if (empty($parameterName)) {
                throw new \InvalidArgumentException('Each lab result row must include a parameter name or parameter id.');
            }

            $lookup = [
                'patient_visit_lab_id' => $test->id,
                'test' => $parameterName,
            ];

            if ($parameter?->id) {
                $lookup['lab_parameter_id'] = $parameter->id;
            }

            LaboratoryResult::updateOrCreate(
                $lookup,
                [
                    'tenant_id'        => $tenantId,
                    'visit_id'         => $test->visit_id,
                    'result'           => $item['result'] ?? null,
                    'unit'             => $item['unit'] ?? $parameter?->unit,
                    'reference_range'  => $item['reference_range'] ?? $parameter?->reference_range,
                    'flag'             => $item['flag'] ?? null,
                    'sensitivity_s'    => $item['sensitivity_s'] ?? false,
                    'sensitivity_r'    => $item['sensitivity_r'] ?? false,
                    'display_order'    => $item['display_order'] ?? $parameter?->display_order ?? $index,
                    'status'           => 'Ready',
                ]
            );
        }

        $fileUrl = null;
        if ($data->signature) {
            if ($data->filled('signature')) {
                $fileUrl = FileUploadHelper::singleStringFileUpload($data->signature, 'signature');
            }
        }

        $test->update([
            'signature' => $fileUrl,
            'specimen_type' => $data->specimen_type,
            'notes'         => $data->notes,
            'user_id'    => $currentUserInstance->id,
            // 'requested_by'  => $data->requested_by,
            'status'   => 'Ready'
        ]);

        // The patient can see the result the moment it is Ready, so this is
        // where they are told. Never allowed to fail the release.
        $this->patientNotifications->resultReleased($test, 'laboratory');

        $visit = PatientVisit::find($test->visit_id);
        $totalLabRequests = Laboratory::where('visit_id', $visit->id)->count();
        $completedLabRequests = Laboratory::where('visit_id', $visit->id)
            ->where('status', GeneralEnums::READY->value)
            ->count();
        if ($totalLabRequests > 0 && $totalLabRequests === $completedLabRequests) {
            $visit->update([
                'lab_status' => GeneralEnums::COMPLETED->value,
            ]);
        }

        $loadRelations = [
            'results.parameter',
            'testService.serviceCategory.labParameters'
        ];

        if ($this->labParameterService->supportsLabTestParameters()) {
            $loadRelations[] = 'testService.labParameters';
        }

        return $test->refresh()->load($loadRelations);
    }

    public function updateTest($data, $test)
    {

        $test->update([
            'status'   => $data->status
        ]);

        return $test->refresh();
    }

    /**
     * Release a laboratory result that was produced outside the system - a
     * scanned report or a PDF - by attaching the file to the test.
     *
     * The file is the result here, so the upload releases the test in the same
     * way entering the result rows does: the test is marked Ready, the patient
     * is notified, and the visit rolls up to Completed once every test on it
     * has been released.
     */
    public function uploadLabResult($data)
    {
        $currentUserInstance = UserMgtHelper::userInstance();

        $test = Laboratory::find($data->input('id'));
        if (!$test) {
            throw new ModelNotFoundException('Lab test not found.');
        }

        $fileUrl = FileUploadHelper::resolveUploadedFile($data, 'laboratory_results');
        if (!$fileUrl) {
            throw new \InvalidArgumentException('No result file was provided.');
        }

        $test->update([
            'file_url'  => $fileUrl,
            'file_name' => FileUploadHelper::resolveUploadedFileName($data, $fileUrl),
            'user_id'   => $currentUserInstance->id,
            'status'    => GeneralEnums::READY->value,
        ]);

        // The patient can see the result the moment it is Ready, so this is
        // where they are told. Never allowed to fail the release.
        $this->patientNotifications->resultReleased($test, 'laboratory');

        $this->markVisitLabCompleteIfDone($test);

        return $test->refresh()->load('results.parameter');
    }

    /**
     * Roll the visit's lab status up to Completed once every lab test ordered
     * on it has been released.
     */
    private function markVisitLabCompleteIfDone($test): void
    {
        $visit = PatientVisit::find($test->visit_id);
        if (!$visit) {
            return;
        }

        $totalLabRequests = Laboratory::where('visit_id', $visit->id)->count();
        $completedLabRequests = Laboratory::where('visit_id', $visit->id)
            ->where('status', GeneralEnums::READY->value)
            ->count();

        if ($totalLabRequests > 0 && $totalLabRequests === $completedLabRequests) {
            $visit->update([
                'lab_status' => GeneralEnums::COMPLETED->value,
            ]);
        }
    }

    public function resultForm($id)
    {
        $record = Laboratory::with([
            'patient',
            'visit',
            'consultation:id,consulted_by',
            'billingLogDetail',
            'results' => function ($query) {
                $query->orderBy('display_order')->orderBy('id');
            },
            'results.parameter',
            'testService.serviceCategory',
            'testService.serviceCategory.labParameters' => function ($query) {
                $query->where('status', true)
                    ->orderBy('display_order')
                    ->orderBy('id');
            }
        ])->find($id);

        if ($record && $this->labParameterService->supportsLabTestParameters()) {
            $record->load([
                'testService.labParameters' => function ($query) {
                    $query->where('status', true)
                        ->orderBy('display_order')
                        ->orderBy('id');
                }
            ]);
        }

        if (!$record) {
            throw new \InvalidArgumentException('Lab test not found.');
        }

        $resultMap = $record->results->keyBy(function ($result) {
            return $result->lab_parameter_id ?: $result->test;
        });

        $effectiveParameters = $this->labParameterService->getEffectiveParametersForLabTest($record->testService);

        $parameterRows = $effectiveParameters->map(function ($parameter) use ($resultMap) {
            $savedResult = $resultMap->get($parameter->id) ?? $resultMap->get($parameter->name);

            return [
                'lab_parameter_id' => $parameter->id,
                'name' => $parameter->name,
                'code' => $parameter->code,
                'result' => $savedResult->result ?? null,
                'unit' => $savedResult->unit ?? $parameter->unit,
                'reference_range' => $savedResult->reference_range ?? $parameter->reference_range,
                'flag' => $savedResult->flag ?? null,
                'input_type' => $parameter->input_type,
                'display_order' => $parameter->display_order,
                'is_required' => (bool) $parameter->is_required,
            ];
        })->values() ?? collect();

        if ($parameterRows->isEmpty()) {
            $parameterRows = $record->results->map(function ($result, $index) {
                return [
                    'lab_parameter_id' => $result->lab_parameter_id,
                    'name' => $result->test,
                    'code' => null,
                    'result' => $result->result,
                    'unit' => $result->unit,
                    'reference_range' => $result->reference_range,
                    'flag' => $result->flag,
                    'input_type' => 'text',
                    'display_order' => $result->display_order ?? $index,
                    'is_required' => false,
                ];
            })->values();
        }

        $selectedTests = Laboratory::where('visit_id', $record->visit_id)
            ->orderBy('id')
            ->get(['id', 'test_id', 'test_name', 'department', 'status']);

        return [
            'lab_request' => $record,
            'selected_tests' => $selectedTests,
            'result_template' => [
                'lab_service_id' => $record->testService?->id,
                'lab_service_name' => $record->testService?->name ?? $record->test_name,
                'service_category' => $record->testService?->serviceCategory?->name ?? $record->department,
                'uses_test_specific_parameters' => $record->testService?->labParameters?->where('status', true)->isNotEmpty() ?? false,
                'selected_specimen_type' => $record->specimen_type,
                'notes' => $record->notes,
                'parameters' => $parameterRows,
            ],
        ];
    }
}
