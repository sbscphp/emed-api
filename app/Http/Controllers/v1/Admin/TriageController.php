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
            // Log activity
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
            $serviceId = $request->query('service_id');

            if (!$serviceId) {
                return JsonResponser::send(true, 'Service ID is required.', null, 400);
            }

            $patients = PatientVisit::join('patients', 'patient_visits.patient_id', '=', 'patients.id')
                ->join('services', 'patients.service_id', '=', 'services.id')
                ->leftJoin('triages', 'patient_visits.patient_id', '=', 'triages.patient_id')
                ->where('services.id', $serviceId)
                ->select(
                    'patient_visits.id as id',
                    'patients.*',
                    'services.id as service_id',
                    'services.name as service_name',
                    'patient_visits.created_at as visit_date',
                    DB::raw('COALESCE(triages.severity, 0) as acuity')
                )
                ->orderBy('patient_visits.created_at', 'desc')
                ->paginate(10);

            if ($patients->isEmpty()) {
                return JsonResponser::send(true, 'No patients found for this service.', [], 404);
            }

            return JsonResponser::send(false, 'Patients fetched successfully', $patients, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function getPatientStatistics($serviceId)
    {
        $today = now()->toDateString();

        $stats = [
            'awaiting_triage' => PatientVisit::where('stage', 'triage')
                ->whereHas('patient', function ($query) use ($serviceId) {
                    $query->where('service_id', $serviceId);
                })
                ->count(),

            'awaiting_consultation' => PatientVisit::where('stage', 'consultation')
                ->whereHas('patient', function ($query) use ($serviceId) {
                    $query->where('service_id', $serviceId);
                })
                ->count(),
            'admitted_today' => PatientVisit::where('stage', 'admitted')
                ->whereDate('created_at', $today)
                ->whereHas('patient', function ($query) use ($serviceId) {
                    $query->where('service_id', $serviceId);
                })
                ->count(),

            'discharged' => PatientVisit::where('stage', 'discharged')
                ->whereHas('patient', function ($query) use ($serviceId) {
                    $query->where('service_id', $serviceId);
                })
                ->count(),

            'completed_surgery' => PatientVisit::where('stage', 'completed_surgery')
                ->whereHas('patient', function ($query) use ($serviceId) {
                    $query->where('service_id', $serviceId);
                })
                ->count(),

            'cancelled_or_postponed' => PatientVisit::whereIn('stage', ['cancelled', 'postponed'])
                ->whereHas('patient', function ($query) use ($serviceId) {
                    $query->where('service_id', $serviceId);
                })
                ->count(),
        ];

        return JsonResponser::send(false, 'Visit Statistics Fetched Successfully', $stats);
    }
}
