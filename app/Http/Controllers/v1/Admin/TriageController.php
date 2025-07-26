<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Enums\PatientVisitStageEnums;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TriageRequest;
use App\Models\Medicine_Log;
use App\Models\Notification;
use App\Responser\JsonResponser;
use App\Services\Patient\PatientService;
use App\Services\PatientVisit\PatientVisitService;
use App\Services\Triage\TriageService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
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
            $serviceId = $request->input('service_id');
            $search = $request->input('search');
            $from = $request->from;
            $to = $request->to;
            $status = $request->status;
            $type =  $request->type;
            $phone_number =  $request->phone_number;
            $payment_status = $request->payment_status;
            $patient_status = $request->patient_status;
            $patient_type = $request->patient_type;
            if (!$serviceId) {
                return JsonResponser::send(true, 'Service ID is required.', null, 400);
            }

            $result = $this->triageService->getPatientsAndStatsByService(
                $serviceId,
                $search,
                $from,
                $to,
                $status,
                $type,
                $phone_number,
                $payment_status,
                $patient_status,
                $patient_type
            );

            return JsonResponser::send(false, 'Patients fetched successfully', [
                'service_id' => $serviceId,
                'stats' => $result['stats'],
                'patients' => $result['patients']
            ], 200);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function getInvestigationOrders(Request $request)
    {
        try {
            $search = $request->input('search');

            $result = $this->triageService->getAllInvestigationOrders($search);

            return JsonResponser::send(false, 'Investigation Orders fetched successfully', [
                'stats' => $result['stats'],
                'patients' => $result['patients']
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
}
