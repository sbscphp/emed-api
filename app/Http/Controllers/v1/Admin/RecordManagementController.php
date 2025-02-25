<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PatientInfomationRequest;
use App\Models\Tenant;
use App\Responser\JsonResponser;
use App\Services\Patient\PatientService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecordManagementController extends Controller
{
    protected $userService;
    protected $patientService;

    public function __construct(UserService $userService, PatientService $patientService)
    {
        $this->userService = $userService;
        $this->patientService = $patientService;
    }

    public function store(PatientInfomationRequest $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();

            $tenant = Tenant::whereDomain($request->getHost())->first();

            if (!$tenant) {
                $tenant = Tenant::where('database','tenant_st_marys_hospitals')->first();
            }

            if ($tenant) {
                $tenant->makeCurrent();
            } else {
                abort(404, 'Tenant not found');
            }

            //$tenant->makeCurrent();

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            // if (!$user->hasRole(['admin', 'records'])) {
            //     return JsonResponser::send(true, 'Forbidden!, User has no permission to register patient', null, 403);
            // }

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
                'recieptno' => $request->recieptno,
            ];

            $patient = $this->patientService->create($data);

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
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500);
        }
    }

    // public function storeNextOfKin(Request $request, $patienId)
    // {
    //     try {
    //         DB::connection('tenant')->beginTransaction();

    //         $tenant = Tenant::whereDomain($request->getHost())->first();

    //         if (is_null($tenant)) {
    //             $tenant = Tenant::first();
    //         }

    //         $tenant->makeCurrent();

    //         $currentUser = Auth::user();
    //         $user = $this->userService->find($currentUser->id);
    //         if (is_null($user)) {
    //             return JsonResponser::send(true, 'User not found.', null, 404);
    //         }


    //         // if (!$user->hasRole(['admin', 'records'])) {
    //         //     return JsonResponser::send(true, 'Forbidden!, User has no permission to register patient', null, 403);
    //         // }

    //         //Prepare data to store
    //         $data = [
    //             'patient_information_id' => $patienId,
    //             'firstname' => $request->firstname,
    //             'lastname' => $request->lastname,
    //             'gender' => $request->gender,
    //             'phoneno' => $request->phoneno,
    //             'stateoforigin' => $request->stateoforigin,
    //             'lga' => $request->lga,
    //             'homeaddress' => $request->homeaddress,
    //             'relationship' => $request->relationship,
    //         ];

    //         $nextOfKin = $this->patientInformationService->createNextOfKin($data);

    //         $dataToLog = [
    //             'causer_id' => $user->id,
    //             'action_id' => $nextOfKin->id,
    //             'action' => 'Create',
    //             'action_type' => "Models\NextOfKin",
    //             'log_name' => "Next of kin created successfully",
    //             'description' => "{$user->firstname} {$user->lastname} created next of kin successfully",
    //         ];

    //         GeneralHelper::storeAuditLog($dataToLog);
    //         DB::connection('tenant')->commit();
    //         return JsonResponser::send(false, 'Next of kin created successfully', $nextOfKin, 200);
    //     } catch (\Throwable $th) {
    //         DB::connection('tenant')->rollBack();
    //         return JsonResponser::send(true, 'Internal server error', $data, 500, $th);
    //     }

    // }

    // public function storeEmergencyContact(Request $request, $patienId)
    // {
    //     try {
    //         DB::connection('tenant')->beginTransaction();

    //         $tenant = Tenant::whereDomain($request->getHost())->first();

    //         if (is_null($tenant)) {
    //             $tenant = Tenant::first();
    //         }

    //         $tenant->makeCurrent();

    //         $currentUser = Auth::user();
    //         $user = $this->userService->find($currentUser->id);
    //         if (is_null($user)) {
    //             return JsonResponser::send(true, 'User not found.', null, 404);
    //         }


    //         // if (!$user->hasRole(['admin', 'records'])) {
    //         //     return JsonResponser::send(true, 'Forbidden!, User has no permission to register patient', null, 403);
    //         // }

    //         //Prepare data to store
    //         $data = [
    //             'patient_information_id' => $patienId,
    //             'firstname' => $request->firstname,
    //             'lastname' => $request->lastname,
    //             'gender' => $request->gender,
    //             'phoneno' => $request->phoneno,
    //             'stateoforigin' => $request->stateoforigin,
    //             'lga' => $request->lga,
    //             'homeaddress' => $request->homeaddress,
    //             'relationship' => $request->relationship,
    //         ];

    //         $emergencyContact = $this->patientInformationService->createNextOfKin($data);

    //         $dataToLog = [
    //             'causer_id' => $user->id,
    //             'action_id' => $nextOfKin->id,
    //             'action' => 'Create',
    //             'action_type' => "Models\NextOfKin",
    //             'log_name' => "Next of kin created successfully",
    //             'description' => "{$user->firstname} {$user->lastname} created next of kin successfully",
    //         ];

    //         GeneralHelper::storeAuditLog($dataToLog);
    //         DB::connection('tenant')->commit();
    //         return JsonResponser::send(false, 'Next of kin created successfully', $nextOfKin, 200);
    //     } catch (\Throwable $th) {
    //         DB::connection('tenant')->rollBack();
    //         return JsonResponser::send(true, 'Internal server error', $data, 500, $th);
    //     }

    // }
}
