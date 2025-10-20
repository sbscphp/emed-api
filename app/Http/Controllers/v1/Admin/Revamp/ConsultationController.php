<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\ListModuleEnums;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConsultationRequest;
use App\Http\Requests\Admin\SurgeryRequest;
use App\Http\Requests\Admin\TreatmentRequest;
use App\Http\Requests\ConsultationLaborartoryRequest;
use App\Http\Requests\ConsultationRadiologyRequest;
use App\Models\Consultation;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Triage;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Revamp\ConsultationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConsultationController extends Controller
{

    protected ConsultationService $consultationService;

    public function __construct(
        ConsultationService $consultationService,
    ) {
        $this->consultationService = $consultationService;
    }

    public function index(Request $request)
    {

        try {
            $overview = $this->consultationService->overview($request);

            $stats = $this->consultationService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];

            if (isset($request->export)) {
                $format = $request->export;
                return $this->consultationService->export($overview, $format);
            }
            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function createConsultation(ConsultationRequest $request)
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

            $consultation = $this->consultationService->createConsultation($request);

            GeneralHelper::storeAuditLog([
                'causer_id'     => $currentUser->id,
                'action_id'     => $request->patient_id,
                'action'        => 'Create',
                'action_type'   => "Models\Consultation",
                'log_name'      => "Consultation recorded successfully",
                'description'   => "{$currentUser->firstname} {$currentUser->lastname} recorded or updated triage details successfully",
                'module_accessed' => ListModuleEnums::Service

            ]);

            // Create notification
            $tenant = $currentUser->currentTenant->first();
            $notificationData = [
                'user_id' => $currentUser->id,
                'tenant_domain' => $tenant->domain,
                'title' => 'Medication Order Received',
                'message' => "A new medication order has been submitted by the doctor following a completed consultation.
                            Please proceed with the following actions:
                            Review the prescribed medication(s) and treatment instructions.
                            Verify correct dosage, check for potential drug interactions, and assess any documented allergies.
                            Dispense or prepare the medication accordingly for patient administration or pickup.
                            Your prompt response ensures safe and efficient delivery of care.",
                'role' => 'Pharmacy',
            ];
            Notification::create($notificationData);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Consultation recorded successfully', $consultation, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function viewConsultation($id)
    {

        try {
            DB::connection('tenant')->beginTransaction();

            $consultation = Consultation::where('visit_id', $id)->with(['patient', 'patientVisit', 'labTest', 'radiologyTest', 'treatment', 'surgery', 'consultedDoctor'])->first();
            if (!$consultation) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            // Manually fetch dispensed user from landlord DB
            if ($consultation->consulted_by) {
                $consultedUser = User::on('landlord')
                    ->select('id', 'first_name', 'last_name', 'email')
                    ->find($consultation->consulted_by);

                $consultation->setAttribute('consultedBy', $consultedUser);
            } else {
                $consultation->setAttribute('consultedBy', null);
            }

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Record found successfully', $consultation, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function recentConsultation($id)
    {
        try {
            $patient = Patient::with(['nextOfKin', 'emergencyContact'])->find($id);

            if (!$patient) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            // Get the most recent completed visit
            $recentVisit = PatientVisit::where('patient_id', $patient->id)
                ->where('status', GeneralEnums::COMPLETED->value)
                ->latest('created_at')
                ->first();

            if (!$recentVisit) {
                return JsonResponser::send(true, 'Patient does not have a recent visit.', null, 200);
            }

            // Recent triage for this visit
            $recentTriage = Triage::where('patient_id', $patient->id)
                ->where('visit_id', $recentVisit->id)
                ->first();

            // Previous 5 completed visits
            $previousVisits = PatientVisit::where('patient_id', $patient->id)
                ->where('status', GeneralEnums::COMPLETED->value)
                ->latest('created_at')
                ->take(5)
                ->get();

            // Recent consultation for this visit
            $recentConsultation = Consultation::where('patient_id', $patient->id)
                ->where('visit_id', $recentVisit->id)
                ->with('treatment')
                ->first();

            $data = [
                "patient"            => $patient,
                "recentVisit"        => $recentVisit,
                "recentTriage"       => $recentTriage,
                "previousFiveVisits"     => $previousVisits,
                "recentConsultation" => $recentConsultation,
            ];

            return JsonResponser::send(false, 'Record found successfully', $data, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function createLabTest(ConsultationLaborartoryRequest $request)
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

            $labTest = $this->consultationService->createLabTest($request);

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $labTest[0]->id,
                'action' => 'Create',
                'action_type' => "Models\Laboratory",
                'log_name' => "Laboratory test created successfully",
                'description' => "{$currentUser['fullname']} created lab test successfully",
                'module_accessed' => ListModuleEnums::Laboratory
            ];
            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Laboratory test recorded successfully', $labTest, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function createRadiologyTest(ConsultationRadiologyRequest $request)
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

            $radiologyTest = $this->consultationService->createRadiologyTest($request);

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $radiologyTest[0]->id,
                'action' => 'Create',
                'action_type' => "Models\Radiology",
                'log_name' => "Radiology test created successfully",
                'description' => "{$currentUser['fullname']} created radiology test successfully",
                'module_accessed' => ListModuleEnums::Radiology
            ];
            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Radiology test recorded successfully', $radiologyTest, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function createTreatment(TreatmentRequest $request)
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

            $drugPrescribed = $this->consultationService->createTreatment($request);

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $drugPrescribed[0]->id,
                'action' => 'Create',
                'action_type' => "Models\Treatment",
                'log_name' => "Treatment created successfully",
                'description' => "{$currentUser['fullname']} created treatment successfully",
                'module_accessed' => ListModuleEnums::PHARMACY
            ];
            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Treatment recorded successfully', $drugPrescribed, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function createSurgery(SurgeryRequest $request)
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

            $surgery = $this->consultationService->createSurgery($request);

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $surgery->id,
                'action' => 'Create',
                'action_type' => "Models\Surgery",
                'log_name' => "Patient surgery created successfully",
                'description' => "{$currentUser['fullname']} created surgery successfully",
                'module_accessed' => ListModuleEnums::PHARMACY
            ];
            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Patient surgery recorded successfully', $surgery, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }
}
