<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PatientInfomationRequest;
use App\Responser\JsonResponser;
use App\Services\PatientInformation\PatientInformationService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecordManagementController extends Controller
{
    protected $userService;
    protected $patientInformationService;

    public function __construct(UserService $userService, PatientInformationService $patientInformationService)
    {
        $this->userService = $userService;
        $this->patientInformationService = $patientInformationService;
    }

    public function storePatient(PatientInfomationRequest $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            if (!$user->hasRole(['admin', 'records'])) {
                return JsonResponser::send(true, 'Forbidden!, User has no permission to register patient', null, 403);
            }

            //Prepare data to store
            $data = [
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'dob' => $request->dob,
                'age' => $request->age,
                'gender' => $request->gender,
                'bloodgroup' => $request->bloodgroup,
                'bloodgenotype' => $request->bloodgenotype,
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
                'receiptno' => $request->receiptno,
            ];

            $patient = $this->patientInformationService->create($data);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $patient->id,
                'action' => 'Create',
                'action_type' => "Models\PatientInformation",
                'log_name' => "Patient created successfully",
                'description' => "{$user->firstname} {$user->lastname} created patient successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Patient created successfully', $patient, 200);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', $patient, 500, $th);
        }
    }
}
