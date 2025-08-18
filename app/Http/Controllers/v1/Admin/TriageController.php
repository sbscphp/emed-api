<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Enums\PatientVisitStageEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TriageRequest;
use App\Models\Laboratory;
use App\Models\Medicine_Log;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Radiology;
use App\Models\Treatment;
use App\Responser\JsonResponser;
use App\Services\Patient\PatientService;
use App\Services\PatientVisit\PatientVisitService;
use App\Services\Triage\TriageService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TriageController extends Controller
{
    protected $triageService;
    protected $userService;
    protected $patientVisitService;
    protected $patientService;

    public function __construct(TriageService $triageService, UserService $userService, PatientVisitService $patientVisitService, PatientService $patientService)
    {
        $this->triageService = $triageService;
        $this->userService = $userService;
        $this->patientVisitService = $patientVisitService;
        $this->patientService = $patientService;
    }

    public function store(TriageRequest $request, $patientId)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $visit = $this->patientVisitService->getByPatientId($patientId);
            if (!$visit) {
                return JsonResponser::send(true, 'Patient not found or visit not yet initiated.', null, 200);
            }

            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            $validated = array_merge($request->validated(), [
                'patient_id' => $patientId,
                'user_id' => $currentUser->id,
            ]);

            $triage = $this->triageService->updateOrCreate(
                ['patient_id' => $patientId],
                $validated
            );

            $this->patientVisitService->updateStage($visit, PatientVisitStageEnums::CONSULTATION);
            $this->patientService->updateNewToExisting();


            $medicine_Log = Medicine_Log::where(['visitno' => $visit->visitno, 'patient_id' => $patientId])->first();

            if ($medicine_Log) {
                $medicine_Log->update([
                    'medication_id' => null,
                    'pharmacy_id' => null,
                    'presscribed_drug' => null,
                    'patient_status' => PatientVisitStageEnums::CONSULTATION,
                    'status' => 'Not Fulfilled',
                    'action' => null
                ]);
            }

            GeneralHelper::storeAuditLog([
                'causer_id'     => $user->id,
                'action_id'     => $patientId,
                'action'        => 'Create/Update',
                'action_type'   => "Models\Patient",
                'log_name'      => "Triage recorded successfully",
                'description'   => "{$user->firstname} {$user->lastname} recorded or updated triage details successfully",
                'module_accessed' => ListModuleEnums::Records

            ]);

            // Create notification
            $tenant = $currentUser->tenant;
            $notificationData = [
                'user_id' => $currentUser->id,
                'tenant_domain' => $tenant->domain,
                'title' => 'New Patient Case Assigned',
                'message' => "Triage for the assigned patient has been successfully completed.
                            You are now expected to proceed with the next clinical step. The following information is available for your review:
                            Recorded vital signs
                            Reported symptoms
                            Triage clinical notes
                            Kindly access the patient’s file to begin consultation.",
                'role' => 'Consultant',
            ];
            Notification::create($notificationData);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Triage recorded successfully', $triage, 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function show($patientId)
    {
        $triage = $this->triageService->getTriageByPatient($patientId);
        return JsonResponser::send(false, 'Patient fetched successfully', $triage, 200);
    }

    public function getPatientsByService(Request $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();

            $serviceId       = $request->input('service_id');
            $search          = $request->input('search');
            $from            = $request->from;
            $to              = $request->to;
            $payment_status  = $request->payment_status;
            $patient_status  = $request->patient_status;
            $patient_type    = $request->patient_type;

            if (!$serviceId) {
                return JsonResponser::send(true, 'Service ID is required.', null, 400);
            }

            $result = $this->triageService->getPatientsAndStatsByService(
                $serviceId,
                $search,
                $from,
                $to,
                $payment_status,
                $patient_status,
                $patient_type
            );

            // Handle export
            if ($request->export) {
                // Work on patients collection
                $exportData = $result['query']->get()->map(function ($p) {
                    return [
                        'Firstname'      => $p->firstname ?? '',
                        'Lastname'       => $p->lastname ?? '',
                        'Card No'        => $p->cardno ?? '',
                        'Patient Type'   => $p->patient_type ?? '',
                        'Patient No'     => $p->patientno ?? '',
                        'Arrival Date'   => $p->arrival_date ?? '',
                        'Departure Date' => $p->departure_date ?? '',
                        'Acuity'         => $p->acuity ?? '',
                        'Patient Status' => $p->patient_status ?? '',
                        'Payment Status' => $p->payment_status ?? '',
                        'Payment Method' => $p->payment_method ?? '',
                    ];
                })->toArray();

                $filename = 'patients_export_' . now()->format('Y-m-d_H-i-s');

                if ($request->export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, "{$filename}.csv");
                }

                if ($request->export === 'pdf') {
                    $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                        ->setPaper('A1', 'landscape');
                    return $pdf->download('services.pdf');
                }
            }

            DB::connection('tenant')->commit();

            return JsonResponser::send(false, 'Patients fetched successfully', [
                'service_id' => $serviceId,
                'stats'      => $result['stats'],
                'patients'   => $result['patients']
            ], 200);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }


    public function getInvestigationOrders(Request $request)
    {
        try {
            $search = $request->input('search_param');
            $patientType = $request->input('patient_type');
            $patientStatus = $request->input('patient_status');
            $paymentStatus = $request->input('payment_status');
            $paginate = $request->paginate === "true";
            $limit = $request->limit ?? 10;

            // Get base query + stats
            $query = $this->triageService->investigationOrdersQuery($search, $patientType, $patientStatus, $paymentStatus);
            $stats = $this->triageService->getInvestigationStats($search);

            // Handle pagination or full collection
            $patients = $paginate
                ? $query->paginate($limit)
                : $query->get();

            // Handle export
            if ($request->export) {
                $exportData = $query->get()->map(function ($item) {
                    return [
                        'Patient Name'     => "{$item->firstname} {$item->lastname}",
                        'Card No'          => $item->cardno,
                        'Patient Type'     => $item->patient_type,
                        'Patient No'       => $item->patientno,
                        'Time Of Arrival'  => $item->arrival_date,
                        'Time Of Departure' => $item->departure_date,
                        'Acuity'           => $item->acuity,
                        'Payment Status'   => $item->payment_status ?? 'N/A',
                        'Patient Status'   => $item->patient_status
                    ];
                });

                if ($request->export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'investigation-orders.csv');
                }

                if ($request->export === 'pdf') {
                    $pdf = PDF::loadView('exports.patients', [
                        'patients' => $exportData->toArray()
                    ])->setPaper('A1', 'landscape');
                    return $pdf->download('investigation-orders.pdf');
                }
            }

            return JsonResponser::send(false, 'Investigation Orders fetched successfully', [
                'stats' => $stats,
                'patients' => $patients
            ], 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }


    public function exportTriagePatients(Request $request, string $format)
    {
        $serviceId = $request->input('service_id');
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$serviceId) {
            return JsonResponser::send(true, 'Service ID is required.', null, 400);
        }

        return $this->triageService->exportTriagePatientsByService($serviceId, $format, $search, $startDate, $endDate);
    }

    public function viewRadiologyInvestigationOrders(Request $request, $id)
    {
        try {

            $patient = Patient::with('visits_recent')->find($id);

            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 200);
            }

            $radiologyQuery = Radiology::query()
                ->where('patient_id', $patient->id)
                ->when($request->search_param, function ($query) use ($request) {
                    $query->where('test_name', 'LIKE', '%' . $request->search_param . '%');
                })
                ->when($request->test_status, function ($query) use ($request) {
                    $query->where('test_status', $request->test_status);
                })
                ->with('visit.billingLogsForPatient')
                ->orderBy('id', 'DESC');

            $radiologyTest = $request->paginate === "true"
                ? $radiologyQuery->paginate($request->limit ?? 10)
                : $radiologyQuery->get();

            if ($request->export) {
                // Always work with a collection for exports
                $exportData = $radiologyQuery->get()->map(function ($item) {
                    $billingLog = $item->visit->billingLogsForPatient; // ✅ Get first log or null
                    return [
                        'Scan Type'           => $item->test_name,
                        'Date'   => $item->created_at->toDateTimeString(),
                        'Price'   => optional($billingLog)->grand_total ?? 'N/A',
                        'Preparation Status' => $item->test_status
                    ];
                });

                if ($request->export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'patients-visits.csv');
                }

                if ($request->export === 'pdf') {
                    $pdf = PDF::loadView('exports.patients', ['patients' => $exportData->toArray()])
                        ->setPaper('A1', 'landscape');
                    return $pdf->download('radiology.pdf');
                }
            }

            $data = [
                "patient" => $patient,
                "radiologyTest" => $radiologyTest,
            ];

            return JsonResponser::send(false, 'Record retrieved successfully.', $data, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }

    public function viewLaboratoryInvestigationOrders(Request $request, $id)
    {
        try {

            $patient = Patient::with('visits_recent')->find($id);

            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 200);
            }

            $labTestQuery = Laboratory::query()
                ->where('patient_id', $patient->id)
                ->when($request->search_param, function ($query) use ($request) {
                    $query->where('test_name', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('lab_dept', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('ordered_test', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('others', 'LIKE', '%' . $request->search_param . '%');
                })
                ->when($request->payment_status, function ($query) use ($request) {
                    $query->whereRelation('billingLogs', 'payment_status', $request->payment_status);
                })
                ->when($request->test_status, function ($query) use ($request) {
                    $query->where('test_status', $request->test_status);
                })
                ->with('billingLogs')
                ->orderBy('id', 'DESC');

            $labTest = $request->paginate === "true"
                ? $labTestQuery->paginate($request->limit ?? 10)
                : $labTestQuery->get();

            if ($request->export) {
                // Always work with a collection for exports
                $exportData = $labTestQuery->get()->map(function ($item) {
                    return [
                        'Type Of Test'   => $item->test_name,
                        'Date'           => $item->created_at->toDateTimeString(),
                        'Price'          => $item->billingLogs->grand_total ?? 0,
                        'Test Status'    => $item->test_status,
                    ];
                });

                if ($request->export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'lab-records.csv');
                }

                if ($request->export === 'pdf') {
                    $pdf = PDF::loadView('exports.patients', ['patients' => $exportData->toArray()])
                        ->setPaper('A1', 'landscape');
                    return $pdf->download('lab-records.pdf');
                }
            }

            $data = [
                "patient" => $patient,
                "labTest" => $labTest,
            ];

            return JsonResponser::send(false, 'Record retrieved successfully.', $data, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }

    public function viewPharmacyInvestigationOrders(Request $request, $id)
    {
        try {

            $patient = Patient::with('visits_recent')->find($id);

            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 200);
            }

            $pharmQuery = Treatment::query()
                ->where('patient_id', $patient->id)
                ->when($request->search_param, function ($query) use ($request) {
                    $query->where('drug', 'LIKE', '%' . $request->search_param . '%');
                })
                ->when($request->payment_status, function ($query) use ($request) {
                    $query->whereRelation('billingLogs', 'payment_status', $request->payment_status);
                })
                // ->when($request->test_status, function ($query) use ($request) {
                //     $query->where('test_status', $request->test_status);
                // })
                ->with('visit', 'billingLogs')
                ->orderBy('id', 'DESC');

            $pharmTest = $request->paginate === "true"
                ? $pharmQuery->paginate($request->limit ?? 10)
                : $pharmQuery->get();

            if ($request->export) {
                // Always work with a collection for exports
                $exportData = $pharmQuery->get()->map(function ($item) {
                    return [
                        'Medicine Type'   => $item->drug,
                        'Date'           => $item->created_at->toDateTimeString(),
                        'Price'          => $item->billingLogs->grand_total ?? 0,
                        'Patient Prescription Status'    => $item->billingLogs->payment_status ?? 'N/A',
                    ];
                });

                if ($request->export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'lab-records.csv');
                }

                if ($request->export === 'pdf') {
                    $pdf = PDF::loadView('exports.patients', ['patients' => $exportData->toArray()])
                        ->setPaper('A1', 'landscape');
                    return $pdf->download('pharmacy-records.pdf');
                }
            }

            $data = [
                "patient" => $patient,
                "pharmTest" => $pharmTest,
            ];

            return JsonResponser::send(false, 'Record retrieved successfully.', $data, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }
}
