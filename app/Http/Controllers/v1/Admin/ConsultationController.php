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
            config(['database.default' => 'tenant']);
            DB::connection('tenant');
            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $search = $request->search;
            $sortBy = $request->sortBy ?? "DESC";
            $date = $request->date ?? null;
            $paginate = $request->paginate ?? false;
            $perPage = $request->perPage ?? 10;
            $patient_type = $request->patient_type;
            $stage = $request->stage;
            $status = $request->status;

            $patients = $this->patientVisitService->getPatientForConsultation($search, $sortBy, $date, $paginate, $perPage, $patient_type,  $stage, $status);
            $patient = PatientVisit::with(['patient', 'patient.triage'])->get();

            $exportData = PatientVistResource::collection($patient)->resolve();
            if (!empty($request->export)) {

                $export =  $request->export;

                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'patientsForConsultation.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'patientsForConsultation.pdf');
                }
            }

            if ($patients->isEmpty()) {
                return JsonResponser::send(true, 'Records not found.', null, 200);
            }
            // $patients->load(['patient', 'patient.triage']);

            $response = [
                'patients' => $patients,
                'total' => $patients->count()
            ];

            return JsonResponser::send(false, 'Records found successfully.', $response, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', null, 500, $th);
        }
    }


    public function consultaton_stats()
    {
        $admitted = Consultation::where("admitted", 1)->count();
        $completed =  Consultation::where("admitted", 0)->count();

        $investigation_pending =   Laboratory::where("status", "pending")->count();
        $investigation_complete =   Laboratory::where("status", "complete")->count();

        $procedure_pending =  Radiology::where("test_status", "pending")->count();
        $procedure_complete =  Radiology::where("test_status", "complete")->count();

        $due = Treatment::where('is_surgery', 1)->count();
        $complete =  Treatment::where('surgery', 'complete')->count();
        $data = [
            'admitted' => $admitted,
            'completed' => $completed,
            'investigation_pending' => $investigation_pending,
            'investigation_complete' => $investigation_complete,
            'procedure_pending' => $procedure_pending,
            'procedure_complete' => $procedure_complete,
            'due' => $due,
            'complete' => $complete
        ];
        return JsonResponser::send(false, 'Records found successfully.', $data, 200);
    }


    public function show($visitNo, Request $request)
    {
        try {

            $validate = $request->validate([
                "export" => "nullable|in:pdf,csv",
                "payment_status" => "nullable|string"
            ]);
            config(['database.default' => 'tenant']);
            DB::connection('tenant');
            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $patientVisit = $this->patientVisitService->findByAttribute('visitno', $visitNo);
            if (!$patientVisit) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
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
            $consultation = $this->consultationService->findByAttribute('visitno', $visitNo);
            $previousVisits = $this->patientVisitService->getPatientPreviousVisits($patientVisit->patient_id, $visitNo);
            $patientVisits_data = PatientVisit::with('billingLogsForPatient.serviceType')
                ->where("patient_id", $patientVisit->patient_id)
                ->when(!empty($validate['payment_status']), function ($query) use ($validate) {
                    $query->whereHas('billingLogsForPatient', function ($qu) use ($validate) {
                        $qu->where('payment_status', $validate['payment_status']);
                    });
                })
                ->orderBy('arrival_date', 'desc')
                ->paginate(10);
            // PatientVistConsultationResource::collection($patientVisits)->resolve();
            // // $this->patientVisitService->getPatientVisits($patientVisit->patient_id);
            // $patientVisits_data = [
            //     'data' => PatientVistConsultationResource::collection($patientVisits),
            //     'meta' => [
            //         'current_page' => $patientVisits->currentPage(),
            //         'last_page' => $patientVisits->lastPage(),
            //         'per_page' => $patientVisits->perPage(),
            //         'total' => $patientVisits->total(),
            //         'from' => $patientVisits->firstItem(),
            //         'to' => $patientVisits->lastItem(),
            //     ]
            // ];

            $laboratory = $this->consultationService->findByVisitNoLabOrBoth($visitNo);
            $radiology = $this->consultationService->findByVisitNoRadiologyOrBoth($visitNo);
            $treatment = $this->treatmentService->getConsultationTreatmentByVisitNo($visitNo);

            $data =  PatientVisit::with('billingLogsForPatient.serviceType')->where("patient_id", $patientVisit->patient_id)->orderBy('arrival_date', 'desc')->get();
            if (!empty($validate['export'])) {
                $exportData =  PatientVistConsultationResource::collection($data)->resolve();

                if ($validate['export'] === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
                }

                if ($validate['export'] === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
                }
            }
            $response = [
                'patientVisit' => $patientVisit,
                'previousVisits' => $previousVisits ?? [],
                'visits' => $patientVisits_data ?? [],
                'consultation' => $consultation ?? [],
                'laboratory' => $laboratory->test_name ?? [],
                'radiology' => $radiology->test_name ?? [],
                'treatment' => $treatment ?? []
            ];

            return JsonResponser::send(false, 'Records found successfully.', $response, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', null, 500, $th);
        }
    }





    public function patient_laboratory($patientId)
    {
        try {
            config(['database.default' => 'tenant']);
            DB::connection('tenant');
            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }
            $patient = optional(PatientVisit::where('patient_id', $patientId)->latest()->first());
            $patientVisit = $this->patientVisitService->findByAttribute('visitno', $patient->visitno);
            if (!$patientVisit) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
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
            $consultation = $this->consultationService->findByAttribute('visitno', $patient->visitno);
            $previousVisits = $this->patientVisitService->getPatientPreviousVisits($patientVisit->patient_id, $patient->visitno);
            $patientVisits = $this->patientVisitService->getPatientVisits($patientVisit->patient_id);
            $laboratory = $this->consultationService->findByVisitNoLabOrBoth($patient->visitno);
            $radiology = $this->consultationService->findByVisitNoRadiologyOrBoth($patient->visitno);
            $treatment = $this->treatmentService->getConsultationTreatmentByVisitNo($patient->visitno);

            $response = [
                'patientVisit' => $patientVisit,
                'previousVisits' => $previousVisits ?? [],
                'visits' => $patientVisits ?? [],
                'consultation' => $consultation ?? [],
                'laboratory' => $laboratory->test_name ?? [],
                'radiology' => $radiology->test_name ?? [],
                'treatment' => $treatment ?? []
            ];

            return JsonResponser::send(false, 'Records found successfully.', $response, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', null, 500, $th);
        }
    }


    public function storeConsultationInfo(ConsultationRequest $request, $visitno)
    {
        try {
            config(['database.default' => 'tenant']);
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            //  $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $patient = $this->patientVisitService->findByAttribute('visitno', $visitno);
            if (!$patient) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            //validate if the visit number have a record
            $consultation = $this->consultationService->findByAttribute('visitno', $visitno);
            if ($consultation) {
                return JsonResponser::send(true, 'Record already exist for this visit.', null, 409);
            }

            if (!empty($request->follow_up) && !$request->followUp_date) {
                return JsonResponser::send(true, 'Please kindly provide a date for follow up', null, 422);
            }

            if (!empty($request->referral) && !$request->referral_details) {
                return JsonResponser::send(true, 'Please kindly provide details of the referral', null, 422);
            }

            $complaints = implode(',', $request->complaints);
            $allergies = implode(',', $request->allergy);

            $data = [
                'patient_id' => $patient->patient_id,
                'admin_id' => $user->id,
                'visitno' => $patient->visitno,
                'complaint' => $complaints,
                'complaint_history' => $request->complaint_history,
                'review' => $request->review,
                'diagnosis' => $request->diagnosis,
                'allergy' => $allergies,
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

            if ($consultation) {
                $patient->update(['stage' => PatientVisitStageEnums::INVESTIGATION]);
            }

            //  Medicine_Log
            $medicine_Log = Medicine_Log::where(['visitno' => $patient->visitno, 'patient_id' => $patient->patient_id])->first();

            if ($medicine_Log) {
                $medicine_Log->update([
                    'medication_id' => null,
                    'pharmacy_id' => null,
                    'presscribed_drug' => null,
                    'patient_status' => PatientVisitStageEnums::INVESTIGATION,
                    'status' => 'Not Fulfilled',
                    'action' => null
                ]);
            }


            if (!empty($request->follow_up && !empty($request->followUp_date))) {
                $data = [
                    'patient_id' => $patient->patient_id,
                    'appointment_date' => $request->followUp_date,
                ];
                $this->appointmentService->create($data);
            }

            //Save record if patient is admitted
            if (!empty($request->admitted)) {
                $data = ['patient_id' => $patient->patient_id, 'admission_date' => Carbon::now()];
                $medicine_Log->update([
                    'medication_id' => null,
                    'pharmacy_id' => null,
                    'presscribed_drug' => null,
                    'patient_status' => PatientVisitStageEnums::ADMITTED,
                    'status' => 'Not Fulfilled',
                    'action' => null
                ]);
                $this->admissionService->create($data);
            }

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $consultation->id,
                'action' => 'Create',
                'action_type' => "Models\Patient",
                'log_name' => "Consultation created successfully",
                'description' => "{{$user['fullname']} created consultation successfully",
                'module_accessed' => ListModuleEnums::Records
            ];

            GeneralHelper::storeAuditLog($dataToLog);

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
            return JsonResponser::send(false, 'Consultation created successfully', ['consultation' => $consultation], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function storeLabInfo(LabRequest $request, $visitno)
    {
        try {
            config(['database.default' => 'tenant']);
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }
            $consultation = $this->consultationService->findByAttribute('visitno', $visitno);
            if (!$consultation) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
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
                'payment_status' => 'pending',
                'status' => $request->status
            ];

            $lab = $this->laboratoryService->create($data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $lab->id,
                'action' => 'Create',
                'action_type' => "Models\Laboratory",
                'log_name' => "Lab details created successfully",
                'description' => "{$user['fullname']} created lab details successfully",
                'module_accessed' => ListModuleEnums::Laboratory
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
            config(['database.default' => 'tenant']);
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            // $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $consultation = $this->consultationService->findByAttribute('visitno', $visitno);
            if (!$consultation) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            //Check if investigation is radiology or both
            if (!in_array($consultation->investigation, ['radiology', 'both'])) {
                return JsonResponser::send(true, 'patient is in laboratory.', null, 200);
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
                'description' => "{$user['fullname']} created radiology diagnosis successfully",
                'module_accessed' => ListModuleEnums::Radiology
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Radiology diagnosis created successfully', ['radiology' => $radiology], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function storeTreatmentInfo(TreatmentRequest $request, $visitno)
    {
        try {
            config(['database.default' => 'tenant']);
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $consultation = $this->consultationService->findByAttribute('visitno', $visitno);
            if (!$consultation) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            $treatmentIds = [];
            foreach ($request->medications as $med) {
                $data = [
                    'patient_id' => $consultation->patient_id,
                    'admin_id' => $user->id,
                    'consultation_id' => $consultation->id,
                    'visitno' => $consultation->visitno,
                    'drug_id' => $med['drug_id'],
                    'drug' => $med['drug'],
                    'qualifier' => $med['qualifier'],
                    'dosage' => $med['dosage'],
                    'weight' => $med['weight'],
                    'period' => $med['period'],
                    'duration' => $med['duration'],
                    'route' => $med['route'],
                    'remark' => $med['remark'],
                    'is_surgery' => $med['is_surgery'],
                    'surgery' => $med['surgery']
                    //'pharmacy_id' => $med['pharmacy_id'] ?? null,
                ];
                $treatment = $this->treatmentService->create($data);

                if ($treatment) {
                    $treatmentIds[] = $treatment->id;
                }

                $medicine_Log = Medicine_Log::where(['visitno' => $consultation->visitno, 'patient_id' => $consultation->patient_id])->first();

                if ($medicine_Log) {
                    $medicine_Log->update([
                        'medication_id' => $med['drug_id'],
                        'pharmacy_id' => $med['pharmacy_id'] ?? null,
                        'presscribed_drug' => $med['drug'],
                        'patient_status' => PatientVisitStageEnums::TREATMENT,
                        'status' => 'Fulfilled',
                        'action' => null
                    ]);
                } else {

                    Medicine_Log::create([
                        'patient_id' => $consultation->patient_id,
                        'medication_id' => $med['drug_id'],
                        'pharmacy_id' => $med['pharmacy_id'] ?? null,
                        'presscribed_drug' => $med['drug'],
                        'patient_status' => PatientVisitStageEnums::TREATMENT,
                        'status' => 'Fulfilled',
                        'action' => null,
                        'visitno' => $consultation->visitno,
                        'arrival_date' => now()
                    ]);
                }
            }




            foreach ($treatmentIds as $treatmentId) {
                $dataToLog = [
                    'causer_id' => $user->id,
                    'action_id' => $treatmentId,
                    'action' => 'Create',
                    'action_type' => "Models\Treatment",
                    'log_name' => "Treatment for diagnosis created successfully",
                    'description' => "{$user['fullname']} created treatment for diagnosis successfully",
                    'module_accessed' => ListModuleEnums::Service
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
            config(['database.default' => 'tenant']);
            $request->validate([
                'name' => 'required|string',
                'status' => 'nullable|string',
                'duration' => 'nullable|string'
            ]);

            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            //  $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            //Validate the patient
            $patient = $this->patientService->find($patientId);
            if (!$patient) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
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
                'description' => "{$user['fullname']} created medical history diagnosis successfully",
                'module_accessed' => ListModuleEnums::Service
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
            config(['database.default' => 'tenant']);
            $request->validate([
                'name' => 'required|string',
                'status' => 'nullable|string',
                'duration' => 'nullable|string'
            ]);

            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            // $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            //Validate the patient
            $patient = $this->patientService->find($patientId);
            if (!$patient) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
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
                'description' => "{$user['fullname']} created family history diagnosis successfully",
                'module_accessed' => ListModuleEnums::Service
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
            config(['database.default' => 'tenant']);
            $request->validate([
                'name' => 'required|string',
                'status' => 'nullable|string',
                'duration' => 'nullable|string'
            ]);

            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            //Validate the patient
            $patient = $this->patientService->find($patientId);
            if (!$patient) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
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
                'description' => "{$user['fullname']} created social history diagnosis successfully",
                'module_accessed' => ListModuleEnums::Service
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
            config(['database.default' => 'tenant']);
            $request->validate([
                'name' => 'required|string',
                'status' => 'nullable|string',
                'duration' => 'nullable|string'
            ]);

            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            //Validate the patient
            $patient = $this->patientService->find($patientId);
            if (!$patient) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
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
                'description' => "{$user['fullname']} created drug history diagnosis successfully",
                'module_accessed' => ListModuleEnums::Service
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
