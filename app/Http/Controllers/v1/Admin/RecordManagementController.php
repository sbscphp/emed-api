<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmergencyContactRequest;
use App\Http\Requests\Admin\NextOfkinRequest;
use App\Http\Requests\Admin\PatientInfomationRequest;
use App\Models\Tenant;
use App\Responser\JsonResponser;
use App\Services\EmergencyContact\EmergencyContactService;
use App\Services\NextOfKin\NextOfKinService;
use App\Services\Patient\PatientService;
use App\Services\User\UserService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RecordManagementController extends Controller
{
    protected $userService;
    protected $patientService;
    protected $nextOfKinService;
    protected $emergencyContactService;

    public function __construct(
        UserService $userService,
        PatientService $patientService,
        NextOfKinService $nextOfKinService,
        EmergencyContactService $emergencyContactService
    ) {
        $this->userService = $userService;
        $this->patientService = $patientService;
        $this->nextOfKinService = $nextOfKinService;
        $this->emergencyContactService = $emergencyContactService;
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

            if (!$user->hasRole(['admin'])) {
                return JsonResponser::send(true, 'Forbidden! User has no permission to register a patient', null, 403);
            }


            $tenant = Tenant::find($user->tenant_id);
            $tenantDbName = $tenant->database;

            $tenantAcronym = $this->generateAcronym($tenantDbName);

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
                'patient_type' => 'new',
                'marital_status' => $request->marital_status,
                'phoneno' => $request->phoneno,
                'visitno' => 'VIS' . GeneralHelper::generateUniqueRandomId($user),
                'occupation' => $request->occupation,
                'homeaddress' => $request->homeaddress,
                'companyaddress' => $request->companyaddress,
                'religion' => $request->religion,
                'stateoforigin' => $request->stateoforigin,
                'lga' => $request->lga,
                'tribe' => $request->tribe,
                'cardno' => $request->cardno,
                'status' => 'new',
                'patientno' => 'EMED/' . GeneralHelper::generateUniqueRandomId($user) . '/' . GeneralHelper::generateUniqueRandomId($user) . '/'.$tenantDbName,
                'recieptno' => 'RCP-' . $request->receiptno,
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
            return JsonResponser::send(false, 'Patient details created successfully', $patient, 201);
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

            if (!$user->hasRole(['admin'])) {
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

            if (!$user->hasRole(['admin'])) {
                return JsonResponser::send(true, 'Forbidden! User has no permission to register a patient', null, 403);
            }

            $patient = $this->patientService->find($patienId);
            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 404);
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

            if (!$user->hasRole(['admin'])) {
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

            if (!$user->hasRole(['admin'])) {
                return JsonResponser::send(true, 'Forbidden! User has no permission to register a patient', null, 403);
            }

            $patient = $this->patientService->find($patienId);
            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 404);
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
            return JsonResponser::send(false, 'Emergency contact created successfully', $emergencyContact, 201);
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

            if (!$user->hasRole(['admin'])) {
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


    public function assignServiceToPatient(Request $request, $patienId)
    {
        try {
            $request->validate([
                'service_id' => 'required|integer|exists:services,id'
            ]);

            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            if (!$user->hasRole(['admin'])) {
                return JsonResponser::send(true, 'Forbidden! User has no permission to register a patient', null, 403);
            }

            $patient = $this->patientService->find($patienId);
            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 404);
            }

            $data = [
                'service_id' => $request->service_id,
            ];

            $patientServiceType = $this->patientService->update($data, $patient->id);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $patientServiceType->id,
                'action' => 'Update',
                'action_type' => "Models\Patient",
                'log_name' => "Patient service assigned successfully",
                'description' => "{$user->firstname} {$user->lastname} assigned patient to service successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Patient service assigned successfully', $patientServiceType, 200);
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

            $patientDetails->load(['nextOfKin']);

            return JsonResponser::send(false, 'Record retrieved successfully.', $patientDetails, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }

    public function initiateVisit(Request $request, $id)
    {

        try {

            $request->validate([
                'status' => 'required|string'
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

            $data = [
                'status' => $request->status
            ];

            if ($patient->patient_type === 'new') {
                $data['patient_type'] = 'existing';
            }

            $statusUpdate = $this->patientService->update($data, $id);

            return JsonResponser::send(false, 'Status updated successfully.', $statusUpdate, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }


    public function patientHistory($id) {}

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
            $records->each(function ($record) {
                $record->show_url = route('record.show', ['id' => $record->id]);
            });

            $records->load(['nextOfKin', 'emergencyContact']);

            return JsonResponser::send(false, 'Record(s) found successfully.', $records, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function generateAcronym($name)
    {
        // Split the name into words
        $words = explode(" ", trim($name));

        // Get the first letter of each word and convert to uppercase
        $acronym = "";
        foreach ($words as $word) {
            $acronym .= strtoupper(substr($word, 0, 1));
        }

        return $acronym;
    }
}
