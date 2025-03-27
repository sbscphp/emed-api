<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\PatientVisitStageEnums;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConsultationRequest;
use App\Http\Requests\Admin\LabRequest;
use App\Models\DrugHistory;
use App\Models\FamilyHistory;
use App\Models\MedicalHistory;
use App\Models\SocialHistory;
use App\Responser\JsonResponser;
use App\Services\Admission\AdmissionService;
use App\Services\Appointment\AppointmentService;
use App\Services\Consultation\ConsultationService;
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
    protected $userService;
    protected $patientService;
    protected $patientVisitService;
    protected $consultationService;
    protected $laboratoryService;
    protected $radiologyService;
    protected $treatmentService;
    protected $appointmentService;
    protected $admissionService;
    public function __construct(
        UserService $userService,
        PatientService $patientService,
        PatientVisitService $patientVisitService,
        ConsultationService $consultationService,
        LaboratoryService $laboratoryService,
        RadiologyService $radiologyService,
        TreatmentService $treatmentService,
        AppointmentService $appointmentService,
        AdmissionService $admissionService
    ) {
        $this->userService = $userService;
        $this->patientService = $patientService;
        $this->patientVisitService = $patientVisitService;
        $this->consultationService = $consultationService;
        $this->laboratoryService = $laboratoryService;
        $this->radiologyService = $radiologyService;
        $this->treatmentService = $treatmentService;
        $this->appointmentService = $appointmentService;
        $this->admissionService = $admissionService;
    }

    public function patientsForConsultation(Request $request)
    {
        try {

            DB::connection('tenant');
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $date = $request->date ?? null;

            $patients = $this->patientVisitService->getPatientForConsultation($date);
            if ($patients->isEmpty()) {
                return JsonResponser::send(true, 'Records not found.', null, 404);
            }
            $patients->load(['patient']);

            return JsonResponser::send(false, 'Records found successfully.', $patients, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', null, 500, $th);
        }
    }

    public function show($visitNo)
    {
        try {

            DB::connection('tenant');
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $patientVisit = $this->patientVisitService->findByAttribute('visitno', $visitNo);
            if (!$patientVisit) {
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }
            $patientVisit->load(
                [
                    'patient.nextOfKin',
                    'patient.service',
                    'patient.triage',
                    'patient.medicalHistory',
                    'patient.familyHistory',
                    'patient.socialHistory',
                    'patient.drugHistory',
                ]
            );
            $previousVisits = $this->patientVisitService->getPatientPreviousVisits($patientVisit->patient_id, $visitNo);
            $patientVisits = $this->patientVisitService->getPatientVisits($patientVisit->patient_id);

            $response = [
                'patientVisit' => $patientVisit,
                'previousVisits' => $previousVisits ?? [],
                'visits' => $patientVisits ?? []
            ];

            return JsonResponser::send(false, 'Records found successfully.', $response, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', null, 500, $th);
        }
    }

    public function storeConsultationInfo(ConsultationRequest $request, $visitno)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $patient = $this->patientVisitService->findByAttribute('visitno', $visitno);
            if (!$patient) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            if (!empty($request->follow_up) && !$request->followUp_date) {
                return JsonResponser::send(true, 'Please kindly provide a date for follow up', null, 422);
            }

            if (!empty($request->referral) && !$request->referral_details) {
                return JsonResponser::send(true, 'Please kindly provide details of the referral', null, 422);
            }

            $data = [
                'patient_id' => $patient->patient_id,
                'admin_id' => $user->id,
                'visitno' => $patient->visitno,
                'complaint' => $request->complaints,
                'complaint_history' => $request->complaint_history,
                'review' => $request->review,
                'diagnosis' => $request->diagnosis,
                'allergy' => $request->allergy,
                'disease_pattern' => $request->disease_pattern,
                'disease_type' => $request->disease_type,
                'investigation' => $request->investigation,
                'follow_up' => $request->follow_up ?? 0,
                'followUp_date' => $request->followUp_date,
                'referral' => $request->referral ?? 0,
                'referral_detail' => $request->referral_details,
                'admiited' => $request->admitted ?? 0
            ];

            $consultation = $this->consultationService->create($data);

            if (!empty($request->investigation)) {
                $patient->update(['stage' => PatientVisitStageEnums::INVESTIGATION]);
            }

            if (!empty($request->follow_up && !empty($request->followUp_date))) {
                $data = [
                    'patient_id' => $patient->patient_id,
                    'appointment_date' => $request->followUp_date,
                ];
                $this->appointmentService->create($data);
            }

            //Save record if patient is admitted
            if(!empty($request->admitted)){
                $data = ['patient_id'=>$patient->patient_id,'admission_date' => Carbon::now()];
                $this->admissionService->create($data);
            }

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $consultation->id,
                'action' => 'Create',
                'action_type' => "Models\Patient",
                'log_name' => "Consultation created successfully",
                'description' => "{$user->firstname} {$user->lastname} created consultation successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Consultation created successfully', ['consultation' => $consultation], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function storeLabInfo(LabRequest $request, $visitno)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            //dd($user);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }
            $consultation = $this->consultationService->findByAttribute('visitno', $visitno);
            if (!$consultation) {
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }

            //Check if investigation is lab or both
            if (!in_array($consultation->investigation, ['laboratory', 'both'])) {
                return JsonResponser::send(true, 'Action forbidden.', null, 403);
            }
            $data = [
                'patient_id' => $consultation->patient_id,
                'admin_id' => $user->id,
                'visitno' => $consultation->visitno,
                'consultation_id' => $consultation->id,
                'lab_dept' => $request->lab_dept,
                'test_name' => $request->test_name,
                'ordered_test' => implode(',', $request->ordered_test),
                'others' => $request->others,
                'test_status' => 'pending',
                'payment_status' => 'pending'
            ];

            $lab = $this->laboratoryService->create($data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $lab->id,
                'action' => 'Create',
                'action_type' => "Models\Laboratory",
                'log_name' => "Lab details created successfully",
                'description' => "{$user->firstname} {$user->lastname} created lab details successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Lab test created successfully', ['lab' => $lab], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function storeRadiologyInfo(LabRequest $request, $visitno)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $consultation = $this->consultationService->findByAttribute('visitno', $visitno);
            if (!$consultation) {
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }

            //Check if investigation is radiology or both
            if (!in_array($consultation->investigation, ['radiology', 'both'])) {
                return JsonResponser::send(true, 'Action forbidden.', null, 403);
            }

            $data = [
                'patient_id' => $consultation->patient_id,
                'admin_id' => $user->id,
                'visitno' => $consultation->visitno,
                'consultation_id' => $consultation->id,
                'lab_dept' => $request->lab_dept,
                'test_name' => $request->test_name,
                'ordered_test' => implode(',', $request->ordered_test),
                'others' => $request->others,
                'test_status' => 'pending',
                'payment_status' => 'pending'
            ];

            $radiology = $this->radiologyService->create($data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $radiology->id,
                'action' => 'Create',
                'action_type' => "Models\Radiology",
                'log_name' => "Patient radiology diagnosis created successfully",
                'description' => "{$user->firstname} {$user->lastname} created radiology diagnosis successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Radiology diagnosis created successfully', ['radiology' => $radiology], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function storeTreatmentInfo(Request $request, $visitno)
    {
        try {
            $medications = $request->medications;
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $consultation = $this->consultationService->findByAttribute('visitno', $visitno);
            if (!$consultation) {
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }

            $treatmentIds = [];
            foreach ($medications as $med) {
                $data = [
                    'patient_id' => $consultation->patient_id,
                    'admin_id' => $user->id,
                    'consultation_id' => $consultation->id,
                    'visitno' => $consultation->visitno,
                    'drug' => $med['drug'],
                    'qualifier' => $med['qualifier'],
                    'dosage' => $med['dosage'],
                    'weight' => $med['weight'],
                    'period' => $med['period'],
                    'duration' => $med['duration'],
                    'route' => $med['route'],
                    'remark' => $med['remark']
                ];
                $treatment = $this->treatmentService->create($data);

                if ($treatment) {
                    $treatmentIds[] = $treatment->id;
                }
            }

            foreach ($treatmentIds as $treatmentId) {
                $dataToLog = [
                    'causer_id' => $user->id,
                    'action_id' => $treatmentId,
                    'action' => 'Create',
                    'action_type' => "Models\Treatment",
                    'log_name' => "Treatment for diagnosis created successfully",
                    'description' => "{$user->firstname} {$user->lastname} created treatment for diagnosis successfully",
                ];

                GeneralHelper::storeAuditLog($dataToLog);
            }

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Treatment for diagnosis created successfully', ['treatment' => $treatment], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function storeMedicalHistory(Request $request, $patientId)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'status' => 'nullable|string',
                'duration' => 'nullable|string'
            ]);

            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            //Validate the patient
            $patient = $this->patientService->find($patientId);
            if (!$patient) {
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }

            $uniqueFields = [
                'patient_id' => $patient->id,
                'name' => $request->name
            ];

            $data = [

                'status' => $request->status,
                'duration' => $request->duration
            ];

            $medicalHistory = MedicalHistory::updateOrcreate($uniqueFields, $data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $medicalHistory->id,
                'action' => 'Create',
                'action_type' => "Models\Treatment",
                'log_name' => "Medical history diagnosis created successfully",
                'description' => "{$user->firstname} {$user->lastname} created medical history diagnosis successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Medical history diagnosis created or updated successfully', ['medicalHistory' => $medicalHistory], 201);
        } catch (Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function storeFamilyHistory(Request $request, $patientId)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'status' => 'nullable|string',
                'duration' => 'nullable|string'
            ]);

            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            //Validate the patient
            $patient = $this->patientService->find($patientId);
            if (!$patient) {
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }

            $uniqueFields = [
                'patient_id' => $patient->id,
                'name' => $request->name
            ];

            $data = [

                'status' => $request->status,
                'duration' => $request->duration
            ];

            $familyHistory = FamilyHistory::updateOrcreate($uniqueFields, $data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $familyHistory->id,
                'action' => 'Create',
                'action_type' => "Models\Treatment",
                'log_name' => "Family history diagnosis created successfully",
                'description' => "{$user->firstname} {$user->lastname} created family history diagnosis successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Family history diagnosis created or updated successfully', ['familyHistory' => $familyHistory], 201);
        } catch (Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function storeSocialHistory(Request $request, $patientId)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'status' => 'nullable|string',
                'duration' => 'nullable|string'
            ]);

            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            //Validate the patient
            $patient = $this->patientService->find($patientId);
            if (!$patient) {
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }

            $uniqueFields = [
                'patient_id' => $patient->id,
                'name' => $request->name
            ];

            $data = [

                'status' => $request->status,
                'duration' => $request->duration
            ];

            $socialHistory = SocialHistory::updateOrcreate($uniqueFields, $data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $socialHistory->id,
                'action' => 'Create',
                'action_type' => "Models\Treatment",
                'log_name' => "Social history diagnosis created successfully",
                'description' => "{$user->firstname} {$user->lastname} created social history diagnosis successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Social history diagnosis created or updated successfully', ['socialHistory' => $socialHistory], 201);
        } catch (Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function storeDrugHistory(Request $request, $patientId)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'status' => 'nullable|string',
                'duration' => 'nullable|string'
            ]);

            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            //Validate the patient
            $patient = $this->patientService->find($patientId);
            if (!$patient) {
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }

            $uniqueFields = [
                'patient_id' => $patient->id,
                'name' => $request->name
            ];

            $data = [

                'status' => $request->status,
                'duration' => $request->duration
            ];

            $drugHistory = DrugHistory::updateOrcreate($uniqueFields, $data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $drugHistory->id,
                'action' => 'Create',
                'action_type' => "Models\Treatment",
                'log_name' => "Drug history diagnosis created successfully",
                'description' => "{$user->firstname} {$user->lastname} created drug history diagnosis successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Drug history diagnosis created or updated successfully', ['drugHistory' => $drugHistory], 201);
        } catch (Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }
}
