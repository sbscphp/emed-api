<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\CancelAdmissionRequest;
use App\Http\Requests\Admission\DischargeAdmissionRequest;
use App\Http\Requests\Admission\EmergencyAdmissionRequest;
use App\Http\Requests\Admission\ScheduleAdmissionRequest;
use App\Http\Requests\Admission\StoreAdmissionRequest;
use App\Http\Requests\Admission\TransferAdmissionRequest;
use App\Http\Requests\Admission\UpdateAdmissionRequest;
use App\Http\Resources\AdmissionDetailResource;
use App\Http\Resources\AdmissionResource;
use App\Http\Resources\AdmittedPatientProfileResource;
use App\Http\Resources\PatientVisitHistoryResource;
use App\Responser\JsonResponser;
use App\Services\Revamp\AdmissionService;
use App\Services\Revamp\PatientService;
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

    /**
     * The admissions listing behind the All, Active, Scheduled, Discharged and
     * Cancelled tabs.
     */
    public function index(Request $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $overview = $this->admissionService->overview($request);

            if ($request->has('export') && !empty($request->query('export'))) {
                return $this->admissionService->export($overview, $request->query('export'));
            }

            $stats = $this->admissionService->stats($request);

            $records = [
                ...$stats,
                'data' => $request->paginate
                    ? AdmissionResource::collection($overview)->response()->getData(true)
                    : AdmissionResource::collection($overview),
            ];

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500, $th);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $admission = $this->admissionService->getAdmissionDetails($request, $id);

            if (!$admission) {
                return JsonResponser::send(true, 'Admission record not found.', [], 404);
            }

            return JsonResponser::send(false, 'Record found successfully', new AdmissionDetailResource($admission));
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500, $th);
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
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500, $th);
        }
    }

    /**
     * The bed spaces of a ward, so the form can offer the free ones.
     */
    public function wardBedSpaces(Request $request, $wardId)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $bedSpaces = $this->admissionService->getWardBedSpaces($request, $wardId);

            return JsonResponser::send(false, 'Record(s) found successfully', $bedSpaces);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 422);
        }
    }

    /**
     * The option lists (admission types, departments, wards, statuses) the
     * admission forms are built from.
     */
    public function options(Request $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $options = $this->admissionService->getAdmissionOptions($request);

            return JsonResponser::send(false, 'Record(s) found successfully', $options);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500, $th);
        }
    }

    /**
     * Admit a patient — either completing an admission initiated from a
     * consultation or raising a brand new one.
     */
    public function admitPatient(StoreAdmissionRequest $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $admission = $this->admissionService->admitPatient($request);

            return JsonResponser::send(false, 'Patient admitted successfully', new AdmissionDetailResource($admission), 201);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 422);
        }
    }

    /**
     * Schedule an admission for a future date.
     */
    public function scheduleAdmission(ScheduleAdmissionRequest $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $admission = $this->admissionService->scheduleAdmission($request);

            return JsonResponser::send(false, 'Admission scheduled successfully', new AdmissionDetailResource($admission), 201);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 422);
        }
    }

    /**
     * Admit a patient through the emergency route, registering them first when
     * they are not already on file.
     */
    public function emergencyAdmission(EmergencyAdmissionRequest $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $admission = $this->admissionService->emergencyAdmission($request);

            return JsonResponser::send(false, 'Emergency admission created successfully', new AdmissionDetailResource($admission), 201);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 422);
        }
    }

    public function updateAdmission(UpdateAdmissionRequest $request, $id)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $admission = $this->admissionService->updateAdmission($request, $id);

            return JsonResponser::send(false, 'Admission updated successfully', new AdmissionDetailResource($admission));
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 422);
        }
    }

    /**
     * Move an admitted patient to a different ward and bed.
     */
    public function transferPatient(TransferAdmissionRequest $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $admission = $this->admissionService->transferAdmission($request);

            return JsonResponser::send(false, 'Patient transferred successfully', new AdmissionDetailResource($admission));
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 422);
        }
    }

    public function cancelAdmission(CancelAdmissionRequest $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $admission = $this->admissionService->cancelAdmission($request);

            return JsonResponser::send(false, 'Admission cancelled successfully', new AdmissionDetailResource($admission));
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 422);
        }
    }

    public function dischargePatient(DischargeAdmissionRequest $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $admission = $this->admissionService->dischargePatient($request);

            return JsonResponser::send(false, 'Patient discharged successfully', new AdmissionDetailResource($admission));
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 422);
        }
    }

    /**
     * The Patient Information tab of the admitted patient screen: the header
     * strip, personal information, location information and the emergency
     * contact.
     */
    public function viewPatient(Request $request, $id)
    {
        try {
            $patientDetails = $this->admissionService->getAdmittedPatientProfile($request, $id);

            if (!$patientDetails) {
                return JsonResponser::send(true, 'Patient Record not found.', null, 422);
            }

            return JsonResponser::send(false, 'Record found successfully', new AdmittedPatientProfileResource($patientDetails));
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500, $th);
        }
    }

    /**
     * The patient's visit history, carrying the ward, bed and attending doctor
     * of the admission each visit produced.
     */
    public function viewPatientVisit(Request $request)
    {
        try {
            $overview = $this->patientService->patientVisitOverview($request);

            if ($request->export) {
                $format = $request->export;
                return $this->patientService->patientVisitExport($overview, $format);
            }

            $records = [
                'data' => $request->paginate
                    ? PatientVisitHistoryResource::collection($overview)->response()->getData(true)
                    : PatientVisitHistoryResource::collection($overview),
            ];

            if (!$request->paginate) {
                $records = PatientVisitHistoryResource::collection($overview);
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500, $th);
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
