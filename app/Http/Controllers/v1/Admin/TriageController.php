<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TriageRequest;
use App\Responser\JsonResponser;
use App\Services\Triage\TriageService;
use App\Services\User\UserService;
use App\Models\Patient;
use App\Models\PatientVisit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TriageController extends Controller
{
    protected $triageService;
    protected $userService;

    public function __construct(TriageService $triageService, UserService $userService)
    {
        $this->triageService = $triageService;
        $this->userService = $userService;
    }

    public function store(TriageRequest $request, $patientId)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $visit = PatientVisit::where('patient_id', $patientId)->first();
            if (!$visit) {
                return JsonResponser::send(true, 'Patient not found or visit not yet initiated.', null, 404);
            }

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);

            if (!in_array($user->role, ['Super Admin', 'Admin'])) {
                return JsonResponser::send(false, 'Forbidden! User has no permission to register a patient', [], 403);
            }

            $validated = array_merge($request->validated(), [
                'patient_id' => $patientId,
                'user_id' => $currentUser->id,
            ]);

            $triage = $this->triageService->updateOrCreate(['patient_id' => $patientId], $validated);

            $visit->update(['stage' => 'consultation']);
            Patient::where('patient_type', 'new')->update(['patient_type' => 'existing']);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $patientId,
                'action' => 'Create/Update',
                'action_type' => "Models\Patient",
                'log_name' => "Triage recorded successfully",
                'description' => "{$user->firstname} {$user->lastname} recorded or updated triage details successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);

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
            $serviceId = $request->input('service_id');
            $search = $request->input('search');
            $export = $request->boolean('export', false);

            if (!$serviceId) {
                return JsonResponser::send(true, 'Service ID is required.', null, 400);
            }

            $result = $this->triageService->getPatientsAndStatsByService($serviceId, $search);

            if ($export) {
                $filename = 'triage-patients-' . now()->format('Y-m-d_H-i-s') . '.xlsx';
                return $this->triageService->exportTriagePatients($result['patients'], $filename);
            }

            return JsonResponser::send(false, 'Patients fetched successfully', [
                'service_id' => $serviceId,
                'stats' => $result['stats'],
                'patients' => $result['patients']
            ], 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function getInvestigationOrders(Request $request)
    {
        try {
            $search = $request->input('search');
            $export = $request->boolean('export', false);

            $result = $this->triageService->getAllInvestigationOrders($search);

            if ($export) {
                $filename = 'investigation-orders-' . now()->format('Y-m-d_H-i-s') . '.xlsx';
                return $this->triageService->exportInvestigationOrders($result['patients'], $filename);
            }

            return JsonResponser::send(false, 'Investigation Orders fetched successfully', [
                'stats' => $result['stats'],
                'patients' => $result['patients']
            ], 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }
}
