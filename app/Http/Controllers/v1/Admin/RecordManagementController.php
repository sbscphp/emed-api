<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\PatientVisitStageEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmergencyContactRequest;
use App\Http\Requests\Admin\NextOfkinRequest;
use App\Http\Requests\Admin\PatientInfomationRequest;
use App\Responser\JsonResponser;
use App\Services\Admission\AdmissionService;
use App\Services\Appointment\AppointmentService;
use App\Services\EmergencyContact\EmergencyContactService;
use App\Services\NextOfKin\NextOfKinService;
use App\Services\Patient\PatientService;
use App\Services\PatientVisit\PatientVisitService;
use App\Services\User\UserService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Models\Tenant;

class RecordManagementController extends Controller
{
    protected $userService;
    protected $patientService;
    protected $nextOfKinService;
    protected $emergencyContactService;
    protected $admissionService;
    protected $appointmentService;
    protected $patientVisitService;

    public function __construct(
        UserService $userService,
        PatientService $patientService,
        NextOfKinService $nextOfKinService,
        EmergencyContactService $emergencyContactService,
        AdmissionService $admissionService,
        AppointmentService $appointmentService,
        PatientVisitService $patientVisitService,
    ) {
        $this->userService = $userService;
        $this->patientService = $patientService;
        $this->nextOfKinService = $nextOfKinService;
        $this->emergencyContactService = $emergencyContactService;
        $this->admissionService = $admissionService;
        $this->appointmentService = $appointmentService;
        $this->patientVisitService = $patientVisitService;
    }

    public function store(PatientInfomationRequest $request)
    {

        try {
            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            //Validate if user has permission to register new patient
            if (!$this->userHasPermission($user)) {
                return JsonResponser::send(true, 'Forbidden! User has no permission to register a patient', null, 403);
            }

            // Validate if patient firstname and lastname exists already
            $patientExists = $this->patientService->findUserByFirstnameAndLastname($request->firstname, $request->lastname);
            if ($patientExists) {
                return JsonResponser::send(true, 'A patient with the same firstname and lastname already exists.', null, 422);
            }

            //validate if Card number exists already
            $cardNoExists = $this->patientService->findByAttribute('cardno', $request->cardno);
            if ($cardNoExists) {
                return JsonResponser::send(true, 'Card Number already exists.', null, 422);
            }

            $image = $request->image ? FileUploadHelper::singleStringFileUpload($request->image, 'Patient') : null;

            $tenant = Tenant::current(); //Retrieve the current tenant
            $tenantDomain = $tenant ? $tenant->domain : 'emed'; // Current tenant domain name
            $tenantAcronym = $this->generateAcronym($tenantDomain); //Acronym for the hospital name()

            //Prepare data to store
            $data = [
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'dob' => $request->dob,
                'age' => $request->age,
                'gender' => $request->gender,
                'bloodgroup' => $request->bloodgroup,
                'genotype' => $request->genotype,
                'email' => $request->email,
                'patient_type' => $request->patient_type,
                'marital_status' => $request->marital_status,
                'phoneno' => $request->phoneno,
                'occupation' => $request->occupation,
                'homeaddress' => $request->homeaddress,
                'companyaddress' => $request->companyaddress,
                'religion' => $request->religion,
                'stateoforigin' => $request->stateoforigin,
                'lga' => $request->lga,
                'tribe' => $request->tribe,
                'cardno' => $request->cardno,
                'patientno' => 'EMED/' . GeneralHelper::generateUniqueRandomId($request->firstname) . '/' . GeneralHelper::generateUniqueRandomId($request->lastname) . '/' . $tenantAcronym,
                'recieptno' => $tenantAcronym . '-' . $request->receiptno,
                'service_id' => $request->service_id,
                'image' => $image,
                'status' => $request->status
            ];

            $patient = $this->patientService->create($data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $patient->id,
                'action' => 'Create',
                'action_type' => "Models\Patient",
                'log_name' => "Patient details created successfully",
                'description' => "{$user->firstname} {$user->lastname} created patient details successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Patient details created successfully', ['patient' => $patient], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            //Validate if user has permission to register new patient
            if (!$this->userHasPermission($user)) {
                return JsonResponser::send(true, 'Forbidden! User has no permission to register a patient', null, 403);
            }

            $patientInfo = $this->patientService->find($id);
            if (is_null($patientInfo)) {
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }

            $image = $request->image ? FileUploadHelper::singleStringFileUpload($request->image, 'Patient') : null;

            //Prepare data to store
            $data = [
                'email' => $request->email ?? $patientInfo->email,
                'patient_type' => $request->patient_type ?? $patientInfo->patient_type,
                'marital_status' => $request->marital_status ?? $patientInfo->marital_status,
                'phoneno' => $request->phoneno ?? $patientInfo->phoneno,
                'occupation' => $request->occupation ?? $patientInfo->occupation,
                'homeaddress' => $request->homeaddress ?? $patientInfo->homeaddress,
                'stateoforigin' => $request->stateoforigin ?? $patientInfo->stateoforigin,
                'lga' => $request->lga ?? $patientInfo->lga,
                'tribe' => $request->tribe ?? $patientInfo->tribe,
                'bloodgroup' => $request->bloodgroup ?? $patientInfo->bloodgroup,
                'genotype' => $request->genotype ?? $patientInfo->genotype,
                'image' => $image ?? $patientInfo->image,
            ];

            $updatePatientDetails = $this->patientService->update($data, $id);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $updatePatientDetails->id,
                'action' => 'Update',
                'action_type' => "Models\Patient",
                'log_name' => "Patient details updated successfully",
                'description' => "{$user->firstname} {$user->lastname} updated patient details successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Patient details updated successfully', $updatePatientDetails, 200);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function addNextOfKin(NextOfkinRequest $request, $patienId)
    {
        try {

            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();

            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            //Validate if user has permission to register new patient
            if (!$this->userHasPermission($user)) {
                return JsonResponser::send(true, 'Forbidden! User has no permission to register a patient', null, 403);
            }

            $patient = $this->patientService->find($patienId);
            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 404);
            }

            // Check if the patient already has a next of kin
            $existingNextOfKin = $this->nextOfKinService->findByAttribute('patient_id', $patient->id);
            if ($existingNextOfKin) {
                return JsonResponser::send(true, 'Patient already has a next of kin.', null, 422);
            }

            //Prepare data to store
            $data = [
                'patient_id' => $patient->id,
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'gender' => $request->gender,
                'phoneno' => $request->phoneno,
                'stateoforigin' => $request->stateoforigin,
                'lga' => $request->lga,
                'homeaddress' => $request->homeaddress,
                'relationship' => $request->relationship,
            ];

            $nextOfKin = $this->nextOfKinService->create($data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $nextOfKin->id,
                'action' => 'Create',
                'action_type' => "Models\NextOfKin",
                'log_name' => "Next of kin created successfully",
                'description' => "{$user->firstname} {$user->lastname} created next of kin successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Next of kin created successfully', $nextOfKin, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function updateNextOfKin(Request $request, $id)
    {
        try {

            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            //Validate if user has permission to register new patient
            if (!$this->userHasPermission($user)) {
                return JsonResponser::send(true, 'Forbidden! User has no permission to register a patient', null, 403);
            }

            $nextOfKinInfo = $this->nextOfKinService->find($id);
            if (is_null($nextOfKinInfo)) {
                return JsonResponser::send(true, 'Record not found', null, 404);
            }

            //Prepare data to store
            $data = [
                'firstname' => $request->firstname ?? $nextOfKinInfo->firstname,
                'lastname' => $request->lastname ?? $nextOfKinInfo->lastname,
                'gender' => $request->gender ?? $nextOfKinInfo->gender,
                'phoneno' => $request->phoneno ?? $nextOfKinInfo->phoneno,
                'stateoforigin' => $request->stateoforigin ?? $nextOfKinInfo->stateoforigin,
                'lga' => $request->lga ?? $nextOfKinInfo->lga,
                'homeaddress' => $request->homeaddress ?? $nextOfKinInfo->homeaddress,
                'relationship' => $request->relationship ?? $nextOfKinInfo->relationship,
            ];

            $updateNextOfKin = $this->nextOfKinService->update($data, $id);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $updateNextOfKin->id,
                'action' => 'Update',
                'action_type' => "Models\NextOfKin",
                'log_name' => "Next of kin updated successfully",
                'description' => "{$user->firstname} {$user->lastname} updated next of kin successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Next of kin updated successfully', $updateNextOfKin, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function addEmergencyContact(EmergencyContactRequest $request, $patienId)
    {

        try {
            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

             //Validate if user has permission to register new patient
             if (!$this->userHasPermission($user)) {
                return JsonResponser::send(true, 'Forbidden! User has no permission to register a patient', null, 403);
            }

            $patient = $this->patientService->find($patienId);
            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 404);
            }

            // Check if the patient already has a next of kin
            $existingContact = $this->emergencyContactService->findByAttribute('patient_id', $patient->id);
            if ($existingContact) {
                return JsonResponser::send(true, 'Patient already has an emergency contact', null, 422);
            }

            //Prepare data to store
            $data = [
                'patient_id' => $patient->id,
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'gender' => $request->gender,
                'phoneno' => $request->phoneno,
                'stateoforigin' => $request->stateoforigin,
                'lga' => $request->lga,
                'homeaddress' => $request->homeaddress,
                'relationship' => $request->relationship,
            ];

            $emergencyContact = $this->emergencyContactService->create($data);

            if($emergencyContact && $request->status === 'complete'){

                $data = [
                    'patient_id' => $patient->id,
                    'visitno' => 'VIS' . GeneralHelper::generateUniqueRandomId($request->firstname),
                    'stage' => PatientVisitStageEnums::TRIAGE,
                    'status' => PatientVisitStatusEnums::ONGOING,
                    'arrival_date' => now(),
                ];
                $patientVisit = $this->patientVisitService->create($data);

                $patient->update(['status'=> $request->status]); //Update the status of the patient to complete

            }

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $emergencyContact->id,
                'action' => 'Create',
                'action_type' => "Models\EmergencyContact",
                'log_name' => "Emergency contact created successfully",
                'description' => "{$user->firstname} {$user->lastname} created emergency successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Emergency contact created successfully', ['emergencyContact'=>$emergencyContact, 'patientVisit'=>$patientVisit], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function updateEmergencyContact(Request $request, $id)
    {
        try {

            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            //Validate if user has permission to register new patient
            if (!$this->userHasPermission($user)) {
                return JsonResponser::send(true, 'Forbidden! User has no permission to register a patient', null, 403);
            }

            $emergencyContactInfo = $this->nextOfKinService->find($id);
            if (is_null($emergencyContactInfo)) {
                return JsonResponser::send(true, 'Record not found', null, 404);
            }

            //Prepare data to store
            $data = [
                'firstname' => $request->firstname ?? $emergencyContactInfo->firstname,
                'lastname' => $request->lastname ?? $emergencyContactInfo->lastname,
                'gender' => $request->gender ?? $emergencyContactInfo->gender,
                'phoneno' => $request->phoneno ?? $emergencyContactInfo->phoneno,
                'stateoforigin' => $request->stateoforigin ?? $emergencyContactInfo->stateoforigin,
                'lga' => $request->lga ?? $emergencyContactInfo->lga,
                'homeaddress' => $request->homeaddress ?? $emergencyContactInfo->homeaddress,
                'relationship' => $request->relationship ?? $emergencyContactInfo->relationship,
            ];

            $updateEmergencyContact = $this->emergencyContactService->update($data, $id);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $updateEmergencyContact->id,
                'action' => 'Update',
                'action_type' => "Models\NextOfKin",
                'log_name' => "Emergency contact updated successfully",
                'description' => "{$user->firstname} {$user->lastname} updated emergency contact successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Emergency contact updated successfully', $updateEmergencyContact, 200);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function show($id)
    {
        try {
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);

            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $patientDetails = $this->patientService->find($id);

            if (is_null($patientDetails)) {
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }

            $patientDetails->load(['nextOfKin', 'emergencyContact', 'visits', 'service']);

            return JsonResponser::send(false, 'Record retrieved successfully.', $patientDetails, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }

    public function initiateVisit(Request $request, $id)
    {

        try {

            $request->validate([
                'stage' => 'required|string'
            ]);
            DB::connection('tenant');

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            $patient = $this->patientService->find($id);
            if (is_null($patient)) {
                return JsonResponser::send(true, 'Record not found.', null, 404);
            }

            if ($patient->status === 'draft') {
                return JsonResponser::send(true, 'Action forbidden. Registeration not complete', null, 403);
            }

            // Check if the patient already has a visit today
            $existingVisit = $this->patientVisitService->findByMultiAttributes([
                ['patient_id', '=', $patient->id],
                ['status', '=', 'ongoing'],
            ]);

            if ($existingVisit) {
                return JsonResponser::send(false, 'A visit is already ongoing for this patient.', null, 422);
            }

            $visitData = [
                'patient_id' => $patient->id,
                'arrival_date' => now(),
                'visitno' => 'VIS' . GeneralHelper::generateUniqueRandomId($patient->firstname),
                'stage' => $request->stage,
                'status' => PatientVisitStatusEnums::ONGOING,
            ];
            $recordVisit = $this->patientVisitService->create($visitData);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $recordVisit->id,
                'action' => 'Create',
                'action_type' => "Models\PatientVisit",
                'log_name' => "Patient visit created successfully",
                'description' => "{$user->firstname} {$user->lastname} created patient visit successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Visit created successfully.', ['visitRecord' => $recordVisit], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function allRecords(Request $request)
    {
        try {
            DB::connection('tenant');

            $search = $request->search;
            $paginate = $request->paginate ?? false;
            $perPage = $request->perPage ?? 1;

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(false, 'User not found.', null, 404);
            }

            $records = $this->patientService->getAllRecords($search, $paginate, $perPage);
            if ($records->isEmpty()) {
                return JsonResponser::send(false, 'Record(s) not found.', null, 404);
            }
            // Load relationships
            $records->load(['service', 'visits']);


            $records->each(function ($record) {

                $record->show_url = route('record.show', ['id' => $record->id]);

                // Get the latest visit
                $latestVisit = $record->visits->sortByDesc('created_at')->first();
                $record->latest_visit = $latestVisit;
            });

            return JsonResponser::send(false, 'Record(s) found successfully.', $records, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function recordStats()
    {
        try {
            $stats = $this->patientService->getRecordStats();

            return JsonResponser::send(false, 'Stats', $stats, 200);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function generateAcronym($name)
    {
        // Trim any leading or trailing spaces
        $name = trim($name);

        // Get the first two letters of the name
        $firstTwoLetters = substr($name, 0, 2);

        // Convert to uppercase and append 'H'
        $acronym = strtoupper($firstTwoLetters) . 'H';

        return $acronym;
    }

    private function userHasPermission($user)
    {
        return $user->role === 'Admin' && $user->is_active && $user->is_verified && $user->tenant_id !== null;
    }
}
