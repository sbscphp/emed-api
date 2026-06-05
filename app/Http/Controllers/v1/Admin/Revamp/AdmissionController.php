<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Revamp\AdmissionService;
use App\Services\Revamp\PatientService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Throwable;

class AdmissionController extends Controller
{
    protected AdmissionService $admissionService;
    protected PatientService $patientService;

    public function __construct(AdmissionService $admissionService, PatientService $patientService)
    {
        $this->admissionService = $admissionService;
        $this->patientService = $patientService;
    }

    public function index(Request $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $overview = $this->admissionService->overview($request);
            $stats = $this->admissionService->stats($request);

            $records = [
                ...$stats,
                'data' => $overview,
            ];

            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $admission = $this->admissionService->getAdmissionDetails($request, $id);

            if (!$admission) {
                return JsonResponser::send(true, 'Admission record not found.', [], 404);
            }

            return JsonResponser::send(false, 'Record found successfully', $admission);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function wards(Request $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $wards = $this->admissionService->getWards($request);

            return JsonResponser::send(false, 'Record(s) found successfully', $wards);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function admitPatient(Request $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $admission = $this->admissionService->admitPatient($request);

            return JsonResponser::send(false, 'Ward assigned successfully', $admission);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function dischargePatient(Request $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $admission = $this->admissionService->dischargePatient($request);

            return JsonResponser::send(false, 'Patient discharged successfully', $admission);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function viewPatient($id)
    {
        try {
            $patientDetails = Patient::with(['nextOfKin', 'emergencyContact'])->find($id);
            if (!$patientDetails) {
                return JsonResponser::send(true, 'Patient Record not found.', null, 422);
            }
            $patientDetails->visit_date = $patientDetails->visits_recent ? Carbon::parse($patientDetails->visits_recent->arrival_date) : null;

            return JsonResponser::send(false, 'Record found successfully', $patientDetails);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function viewPatientVisit(Request $request)
    {
        try {
            $overview = $this->patientService->patientVisitOverview($request);

            $records = [
                'data' => $overview
            ];

            if ($request->export) {
                $format = $request->export;
                return $this->patientService->patientVisitExport($overview, $format);
            }
            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function patientCareNotes(Request $request)
    {
        try {
            $careNotes = $this->admissionService->getPatientCareNotes($request);

            $records = [
                'data' => $careNotes
            ];

            if ($request->export) {
                $format = $request->export;
                return $this->admissionService->exportPatientCareNotes($careNotes, $format);
            }

            if (!$request->paginate) {
                $records = $careNotes;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function addPatientCareNotes(Request $request)
    {
        try {
            $careNote = $this->admissionService->addPatientCareNote($request);

            return JsonResponser::send(false, 'Care note added successfully', $careNote);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function viewPatientCareNotes($id)
    {
        try {
            $records = $this->admissionService->viewPatientCareNotes($id);
            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
    public function updatePatientCareNotes(Request $request, $id)
    {
        try {
            $records = $this->admissionService->updatePatientCareNotes($request, $id);
            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function viewPatientVisitDrugs($id)
    {
        try {
            $records = $this->admissionService->viewPatientVisitDrugs($id);
            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function patientDrugCharts(Request $request)
    {
        try {
            $careNotes = $this->admissionService->getPatientDrugCharts($request);

            $records = [
                'data' => $careNotes
            ];

            if ($request->export) {
                $format = $request->export;
                return $this->admissionService->exportPatientDrugCharts($careNotes, $format);
            }

            if (!$request->paginate) {
                $records = $careNotes;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function addPatientDrugCharts(Request $request)
    {
        try {
            $drugChart = $this->admissionService->addPatientDrugChart($request);

            return JsonResponser::send(false, 'Drug chart added successfully', $drugChart);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function viewPatientDrugCharts($id)
    {
        try {
            $records = $this->admissionService->viewPatientDrugCharts($id);
            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
