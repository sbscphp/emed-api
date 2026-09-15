<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\GeneralEnums;
use App\Enums\ListModuleEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TriageRequest;
use App\Models\BillingLog;
use App\Models\Laboratory;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Radiology;
use App\Models\Treatment;
use App\Models\Triage;
use App\Responser\JsonResponser;
use App\Services\Revamp\PatientVisitService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TriageController extends Controller
{
    protected PatientVisitService $patientVisitService;

    public function __construct(
        PatientVisitService $patientVisitService,
    ) {
        $this->patientVisitService = $patientVisitService;
    }

    public function index(Request $request)
    {

        try {
            $overview = $this->patientVisitService->overview($request);

            $stats = $this->patientVisitService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];

            if (isset($request->export)) {
                $format = $request->export;
                return $this->patientVisitService->export($overview, $format);
            }
            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function store(TriageRequest $request)
    {

        try {
            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();

            $patient = Patient::find($request->patient_id);
            if (!$patient) {
                return JsonResponser::send(true, 'Patient not found.', null, 200);
            }

            $visit = PatientVisit::find($request->visit_id);
            if (!$visit) {
                return JsonResponser::send(true, 'Patient visit not yet initiated.', null, 200);
            }

            // A visit belongs to its day; a prior-day outpatient visit can't be triaged.
            if (\App\Helpers\VisitPolicy::isStaleOutpatient($visit)) {
                DB::connection('tenant')->rollBack();
                // Close it outside the (rolled-back) transaction so the state sticks.
                \App\Helpers\VisitPolicy::closeStaleOutpatient($visit);
                return JsonResponser::send(true, 'This visit is from a previous day. Please initiate a new visit for today.', [], 422);
            }

            // Registration + consultation billing for this visit must be fully paid
            // before the nurse can start triage.
            $visitBilling = BillingLog::where('visit_id', $visit->id)->orderBy('id')->first();
            if (!$visitBilling || $visitBilling->payment_status !== GeneralEnums::PAID->value) {
                DB::connection('tenant')->rollBack();
                return JsonResponser::send(true, 'Registration and consultation billing must be fully paid before triage.', [], 402);
            }

            $triage = $this->patientVisitService->create($request);

            GeneralHelper::storeAuditLog([
                'causer_id'     => $currentUser->id,
                'action_id'     => $request->patient_id,
                'action'        => 'Create',
                'action_type'   => "Models\Triage",
                'log_name'      => "Triage recorded successfully",
                'description'   => "{$currentUser->firstname} {$currentUser->lastname} recorded or updated triage details successfully",
                'module_accessed' => ListModuleEnums::Service

            ]);

            // Create notification
            //$tenant = $currentUser->tenant;
            $tenant = \App\Models\Tenant::current();
            $notificationData = [
                'user_id' => $currentUser->id,
                'tenant_domain' => $tenant?->domain,
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
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function showService($id)
    {
        try {
            $triage = Triage::where('visit_id', $id)->with('patient.nextOfKin', 'patient.emergencyContact')->first();
            if (!$triage) {
                return JsonResponser::send(true, 'Triage record not found.', null, 200);
            }
            $triage->visit_date = $triage->patient->visits_recent ? Carbon::parse($triage->patient->visits_recent->arrival_date) : null;
            return JsonResponser::send(false, 'Triage record found successfully', $triage);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function investigationOrders(Request $request)
    {

        try {
            $overview = $this->patientVisitService->investigationOrdersOverview($request);

            $stats = $this->patientVisitService->investigationOrdersStats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];

            if (isset($request->export)) {
                $format = $request->export;
                return $this->patientVisitService->investigationOrdersExport($overview, $format);
            }
            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function viewRadiologyInvestigationOrders(Request $request, $id, $visit)
    {
        try {

            $patient = Patient::with('visits_recent')->find($id);

            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 200);
            }

            $radiologyQuery = Radiology::query()
                ->where('patient_id', $patient->id)
                ->where('visit_id', $visit)
                ->when($request->search_param, function ($query) use ($request) {
                    $query->where('test_name', 'LIKE', '%' . $request->search_param . '%');
                })
                ->when($request->test_status, function ($query) use ($request) {
                    $query->where('status', $request->test_status);
                })
                ->with('visit.billingLogsForPatient', 'billingLogDetail')
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
                        'Price'   => $item->billingLogDetail->amount ?? 0,
                        'Preparation Status' => $item->status
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

    public function viewLaboratoryInvestigationOrders(Request $request, $id, $visit)
    {
        try {

            $patient = Patient::with('visits_recent')->find($id);

            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 200);
            }

            $labTestQuery = Laboratory::query()
                ->where('patient_id', $patient->id)
                ->where('visit_id', $visit)
                ->when($request->search_param, function ($query) use ($request) {
                    $query->where('test_name', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('department', 'LIKE', '%' . $request->search_param . '%')
                        ->orWhere('specimen_type', 'LIKE', '%' . $request->search_param . '%');
                })
                ->when($request->payment_status, function ($query) use ($request) {
                    $query->whereRelation('billingLogs', 'payment_status', $request->payment_status);
                })
                ->when($request->test_status, function ($query) use ($request) {
                    $query->where('status', $request->test_status);
                })
                ->with('billingLogs', 'billingLogDetail')
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
                        'Price'          => $item->billingLogDetail->amount ?? 0,
                        'Test Status'    => $item->status,
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

    public function viewPharmacyInvestigationOrders(Request $request, $id, $visit)
    {
        try {

            $patient = Patient::with('visits_recent')->find($id);

            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 200);
            }

            $pharmQuery = Treatment::query()
                ->where('patient_id', $patient->id)
                ->where('visit_id', $visit)
                ->when($request->search_param, function ($query) use ($request) {
                    $query->where('drug', 'LIKE', '%' . $request->search_param . '%');
                })
                ->when($request->payment_status, function ($query) use ($request) {
                    $query->whereRelation('billingLogs', 'payment_status', $request->payment_status);
                })
                ->when($request->status, function ($query) use ($request) {
                    $query->where('status', $request->status);
                })
                ->with('visit', 'billingLogs', 'billingLogDetail')
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
                        'Price'          => $item->billingLogDetail->amount ?? 0,
                        'Patient Prescription Status'    => $item->status,
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
