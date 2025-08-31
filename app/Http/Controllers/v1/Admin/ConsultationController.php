<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Enums\PatientVisitStageEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConsultationRequest;
use App\Http\Requests\Admin\LabRequest;
use App\Http\Requests\Admin\TreatmentRequest;
use App\Http\Requests\ConsultationLaborartoryRequest;
use App\Http\Resources\PatientVistConsultationResource;
use App\Http\Resources\PatientVistResource;
use App\Models\Consultation;
use App\Models\DrugHistory;
use App\Models\FamilyHistory;
use App\Models\Laboratory;
use App\Models\MedicalHistory;
use App\Models\Medicine_Log;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Radiology;
use App\Models\SocialHistory;
use App\Models\Treatment;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Admission\AdmissionService;
use App\Services\Appointment\AppointmentService;
use App\Services\Revamp\ConsultationService;
use App\Services\Laboratory\LaboratoryService;
use App\Services\Patient\PatientService;
use App\Services\PatientVisit\PatientVisitService;
use App\Services\Radiology\RadiologyService;
use App\Services\Treatment\TreatmentService;
use App\Services\User\UserService;
use Carbon\Carbon;
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

    public function storeConsultationInfo(ConsultationRequest $request)
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
            $tenant = $currentUser->tenant;
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

    public function createLabTestConsultation(ConsultationLaborartoryRequest $request)
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

            $consultation = $this->consultationService->createLabTestConsultation($request);

            GeneralHelper::storeAuditLog([
                'causer_id'     => $currentUser->id,
                'action_id'     => $request->patient_id,
                'action'        => 'Create',
                'action_type'   => "Models\Consultation",
                'log_name'      => "Consultation recorded successfully",
                'description'   => "{$currentUser->firstname} {$currentUser->lastname} recorded or updated triage details successfully",
                'module_accessed' => ListModuleEnums::Service

            ]);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Consultation recorded successfully', $consultation, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }
}
