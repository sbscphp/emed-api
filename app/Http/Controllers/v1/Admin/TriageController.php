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
            $visit = PatientVisit::where('patient_id', $patientId)->where('status', 'new')->first();
            if (!$visit) {
                return JsonResponser::send(true, 'Patient not found or visit not yet initiated.', null, 404);
            }

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);

            if (!$user->hasRole(['admin'])) {
                return JsonResponser::send(true, 'Forbidden! User has no permission to initialize Triage', null, 403);
            }

            $existingTriage = $this->triageService->find($patientId);
            if ($existingTriage) {
                return JsonResponser::send(true, 'Patient has already been triaged.', null, 409);
            }

            $validated = array_merge($request->validated(), [
                'patient_id' => $patientId,
                'user_id' => $currentUser->id,
            ]);

            $triage = $this->triageService->create($validated);

            $visit->update(['status' => 'triaged']);
            Patient::where('id', $patientId)->update(['status' => 'triaged']);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $patientId,
                'action' => 'Create',
                'action_type' => "Models\Patient",
                'log_name' => "Triage created successfully",
                'description' => "{$user->firstname} {$user->lastname} recorded triage details successfully",
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

            $patients = PatientVisit::join('patients', 'visits.patient_id', '=', 'patients.id')
                ->join('services', 'patients.service_id', '=', 'services.id')
                ->leftJoin('triages', 'visits.patient_id', '=', 'triages.patient_id')
                ->whereIn('patients.status', ['visit initiated', 'triaged'])
                ->where('services.id', $serviceId)
                ->select(
                    'visits.id as id',
                    'patients.*',
                    'services.id as service_id',
                    'services.name as service_name',
                    'visits.created_at as visit_date',
                    DB::raw('COALESCE(triages.severity, 0) as acuity')
                )
                ->orderBy('visits.created_at', 'desc')
                ->paginate(10);

            if ($patients->isEmpty()) {
                return JsonResponser::send(true, 'No patients found for this service.', [], 404);
            }

            return JsonResponser::send(false, 'Patients fetched successfully', $patients, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }


    public function getPatientStatistics()
    {
        $today = now()->toDateString();

        $stats = [
            'awaiting_triage' => Patient::where('status', 'new')->count(),
            'awaiting_consultation' => Patient::where('status', 'triaged')->count(),
            'admitted_today' => Patient::where('status', 'admitted')
                ->whereDate('created_at', $today)
                ->count(),
            'discharged' => Patient::where('status', 'discharged')->count(),
            'completed_surgery' => Patient::where('status', 'completed_surgery')->count(),
            'cancelled_or_postponed' => Patient::whereIn('status', ['cancelled', 'postponed'])->count(),
        ];

        return JsonResponser::send(false, 'Visit Statistics Fetched Successfully', $stats);
    }
}
