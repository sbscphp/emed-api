<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\PatientVisitStageEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
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
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Multitenancy\Models\Tenant;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PatientVisitExport;
use App\Http\Resources\PatientDetailResoures;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;

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
            // $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }


            //  $checkemail = DB::connection('tenant')->table('patients')->where('email', $request->email)->first();
            //  if($checkemail){
            //    return JsonResponser::send(true, 'email already exists.', null, 422);
            //  }


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
                'description' => "{$user['fullname']} created patient details successfully",
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
            config(['database.default' => 'tenant']);
            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();
            // $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }


            $patientInfo = $this->patientService->find($id);
            if (is_null($patientInfo)) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
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
                'description' => "{$user['fullname']} updated patient details successfully",
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
            config(['database.default' => 'tenant']);
            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();

            // $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $patient = $this->patientService->find($patienId);
            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 200);
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
                'description' => "{$user['fullname']} created next of kin successfully",
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
            config(['database.default' => 'tenant']);
            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $nextOfKinInfo = $this->nextOfKinService->find($id);
            if (is_null($nextOfKinInfo)) {
                return JsonResponser::send(true, 'Record not found', null, 200);
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
                'description' => "{$user['fullname']} updated next of kin successfully",
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
            config(['database.default' => 'tenant']);
            DB::connection('tenant')->beginTransaction();
            $validate = $request->validated();
            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }


            $patient = $this->patientService->find($patienId);
            if (is_null($patient)) {
                return JsonResponser::send(true, 'Patient not found.', null, 200);
            }

            // Check if the patient already has a next of kin
            $existingContact = $this->emergencyContactService->findByAttribute('patient_id', $patient->id);
            if ($existingContact) {
                return JsonResponser::send(true, 'Patient already has an emergency contact', null, 200);
            }

            //Prepare data to store
            $data = [
                'patient_id' => $patient->id,
                'firstname' => $validate['firstname'],
                'lastname' => $validate['lastname'],
                'gender' => $validate['gender'],
                'phoneno' => $validate['phoneno'],
                'stateoforigin' => $validate['stateoforigin'],
                'lga' => $validate['lga'],
                'homeaddress' => $validate['homeaddress'],
                'relationship' => $validate['relationship'],
            ];

            $emergencyContact = $this->emergencyContactService->create($data);

            if ($emergencyContact) {

                $data = [
                    'patient_id' => $patient->id,
                    'visitno' => 'VIS' . GeneralHelper::generateUniqueRandomId($validate['firstname']),
                    'stage' => PatientVisitStageEnums::TRIAGE,
                    'status' => PatientVisitStatusEnums::ONGOING,
                    'arrival_date' => now(),
                ];
                $patientVisit = $this->patientVisitService->create($data);

                // $patient->update(['status' => $validate['status']??""]); //Update the status of the patient to complete

            }

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $emergencyContact->id,
                'action' => 'Create',
                'action_type' => "Models\EmergencyContact",
                'log_name' => "Emergency contact created successfully",
                'description' => "{$user['fullname']} created emergency successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Emergency contact created successfully', ['emergencyContact' => $emergencyContact, 'patientVisit' => $patientVisit ?? []], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function updateEmergencyContact(Request $request, $id)
    {
        try {
            config(['database.default' => 'tenant']);
            DB::connection('tenant')->beginTransaction();

            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);
            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $emergencyContactInfo = $this->nextOfKinService->find($id);
            if (is_null($emergencyContactInfo)) {
                return JsonResponser::send(true, 'Record not found', null, 200);
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
                'description' => "{$user['fullname']} updated emergency contact successfully",
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
            // $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $patientDetails = $this->patientService->find($id);

            if (is_null($patientDetails)) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            $data =  $patientDetails->load(['nextOfKin', 'emergencyContact', 'visits', 'service']);
            //  $fetch = PatientDetailResoures::make($data); 

            $data = $patientDetails->load(['nextOfKin', 'emergencyContact', 'visits', 'service', 'billingLogs']);


            $serviceDate = $patientDetails->service->name ?? null;
            $servceid =  $patientDetails->service->id ?? null;

            $data->visits->transform(function ($visit) use ($serviceDate,  $servceid) {
                $visit->service_name = $serviceDate;
                $visit->service_id = $servceid;
                return $visit;
            });
            unset($data->service);

            return JsonResponser::send(false, 'Record retrieved successfully.', collect($data), 200);
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
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $patient = $this->patientService->find($id);
            if (is_null($patient)) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            if ($patient->status === 'draft') {
                return JsonResponser::send(true, 'Action forbidden. Registeration not complete', null, 403);
            }

            // Check if the patient already has a visit today
            $existingVisit = $this->patientVisitService->findByMultiAttributes([
                ['patient_id', '=', $patient->id],
                ['stage', '=', $request->stage],
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
                'description' => "{$user['fullname']} created patient visit successfully",
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Visit created successfully.', ['visitRecord' => $recordVisit], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    // public function allRecords(Request $request)
    // {

    //     //try {
    //     config(['database.default' => 'tenant']);
    //     DB::connection('tenant')->beginTransaction();
    //     $search = $request->search;
    //     $paginate = $request->paginate ?? false;
    //     $perPage = $request->perPage ?? 10;
    //     $from = $request->from;
    //     $to = $request->to;
    //     $export = $request->export ?? "csv";
    //     $gender = $request->gender;
    //     $status = $request->status;
    //     $patient_type = $request->patient_type;
    //     $currentUser = Auth::user();
    //     //$user = $this->userService->find($currentUser->id);
    //     $user = User::where('email', $currentUser['email'] ?? "superadmin@emed.com")->first();

    //     $fileName = 'patients.csv';
    //     $headers = $export == 'csv' ?   [
    //         "Content-type"        =>  "text/csv",
    //         "Content-Disposition" => "attachment; filename=$fileName",
    //         "Pragma"              => "no-cache",
    //         "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
    //         "Expires"             => "0"
    //     ] :   [
    //         "Content-type"        =>  "application/json"
    //     ];

    //     if ($export === 'csv') {




    //         $columns = [
    //             'firstname',
    //             'lastname',
    //             'dob',
    //             'age',
    //             'gender',
    //             'bloodgroup',
    //             'genotype',
    //             'email',
    //             'patient_type',
    //             'marital_status',
    //             'phoneno',
    //             'visitno',
    //             'occupation',
    //             'homeaddress',
    //             'companyaddress',
    //             'religion',
    //             'stateoforigin',
    //             'lga',
    //             'tribe',
    //             'cardno',
    //             'receiptno',
    //             'status',
    //             'service_id',
    //             'arrival_time',
    //             'depature_time',
    //             'patientno'
    //         ];

    //         $callback = function () use ($columns) {
    //             $file = fopen('php://output', 'w');
    //             fputcsv($file, $columns);

    //             // $patients = Patient::all(); // Adjust to your fields

    //             $patients  = Patient::all();
    //             foreach ($patients as $patient) {
    //                 fputcsv($file, [
    //                     $patient->firstname,
    //                     $patient->lastname,
    //                     $patient->dob,
    //                     $patient->age,
    //                     $patient->gender,
    //                     $patient->bloodgroup,
    //                     $patient->genotype,
    //                     $patient->email,
    //                     $patient->patient_type,
    //                     $patient->marital_status,
    //                     $patient->phoneno,
    //                     $patient->visitno,
    //                     $patient->occupation,
    //                     $patient->homeaddress,
    //                     $patient->companyaddress,
    //                     $patient->religion,
    //                     $patient->stateoforigin,
    //                     $patient->lga,
    //                     $patient->tribe,
    //                     $patient->cardno,
    //                     $patient->receiptno,
    //                     $patient->status,
    //                     $patient->service_id,
    //                     $patient->arrival_time,
    //                     $patient->depature_time,
    //                     $patient->patientno
    //                 ]);
    //             }

    //             fclose($file);
    //         };

    //         return response()->stream($callback, 200, $headers);
    //     }


    //     if (is_null($user)) {
    //         return JsonResponser::send(false, 'User not found.', null, 200);
    //     }

    //     $records = $this->patientService->getAllRecordFiltered($search, $paginate, $perPage, $from, $to, $export, $gender, $status, $patient_type);

    //     if ($records->isEmpty()) {
    //         return JsonResponser::send(false, 'Record(s) not found.', null, 200);
    //     }


    //     $records->load([
    //         'service',
    //         'visits_recent',
    //         // 'visits' => function ($query) {
    //         //     $query->select(
    //         //         'id',
    //         //         'patient_id',
    //         //         'visitno',
    //         //         'stage',
    //         //         'status',
    //         //         'arrival_date',
    //         //         'departure_date',
    //         //         'visit_date',
    //         //         'created_at'
    //         //     );
    //         // }
    //     ]);






    //     $summary = $this->patientService->getRecordStats();
    //     return JsonResponser::send(false, 'Record(s) found successfully.', [
    //         // 'records' => $records,
    //         'records' => collect($records),
    //         'summary' => $summary,
    //     ], 200);



    //     // } catch (\Throwable $th) {
    //     //     return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
    //     // }
    //



    public function allRecords(Request $request)
    {
        config(['database.default' => 'tenant']);
        DB::connection('tenant')->beginTransaction();

        try {
            $search = $request->search;
            $paginate = $request->paginate ?? false;
            $perPage = $request->perPage ?? 10;
            $from = $request->from;
            $to = $request->to;
            $export = $request->export;
            $gender = $request->gender;
            $status = $request->status;
            $patient_type = $request->patient_type;
            $currentUser = Auth::user();
            $user = User::where('email', $currentUser['email'])->first();

            if (is_null($user)) {
                DB::connection('tenant')->rollBack();
                return JsonResponser::send(false, 'User not found.', null, 200);
            }

            if ($export === 'csv') {
                $fileName = 'patients.csv';
                $headers =   [
                    "Content-type"        =>  "text/csv",
                    "Content-Disposition" => "attachment; filename=$fileName",
                    "Pragma"              => "no-cache",
                    "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                    "Expires"             => "0"
                ];

                $columns = [
                    'firstname',
                    'lastname',
                    'dob',
                    'age',
                    'gender',
                    'bloodgroup',
                    'genotype',
                    'email',
                    'patient_type',
                    'marital_status',
                    'phoneno',
                    'visitno',
                    'occupation',
                    'homeaddress',
                    'companyaddress',
                    'religion',
                    'stateoforigin',
                    'lga',
                    'tribe',
                    'cardno',
                    'receiptno',
                    'status',
                    'service_id',
                    'arrival_time',
                    'depature_time',
                    'patientno',
                    'arrival_date',
                    'departure_date',
                    'patientvisit_status',
                    'visitno'
                ];

                $callback = function () use ($columns) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, $columns);

                    // $patients = Patient::all(); // Adjust to your fields
                    //    $html .= '<td>' . htmlspecialchars($patientvisit?->arrival_date ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                    //     $html .= '<td>' . htmlspecialchars($patientvisit?->departure_date ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                    //     $html .= '<td>' . htmlspecialchars($patientvisit?->status ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                    //     $html .= '<td>' . htmlspecialchars($patientvisit?->visitno ?? '', ENT_QUOTES, 'UTF-8') . '</td>';

                    $patients  = Patient::all();
                    foreach ($patients as $patient) {
                        $patientvisit =  PatientVisit::where('patient_id', $patient->id)->first();

                        fputcsv($file, [
                            $patient->firstname,
                            $patient->lastname,
                            $patient->dob,
                            $patient->age,
                            $patient->gender,
                            $patient->bloodgroup,
                            $patient->genotype,
                            $patient->email,
                            $patient->patient_type,
                            $patient->marital_status,
                            $patient->phoneno,
                            $patient->visitno,
                            $patient->occupation,
                            $patient->homeaddress,
                            $patient->companyaddress,
                            $patient->religion,
                            $patient->stateoforigin,
                            $patient->lga,
                            $patient->tribe,
                            $patient->cardno,
                            $patient->receiptno,
                            $patient->status,
                            $patient->service_id,
                            $patient->arrival_time,
                            $patient->depature_time,
                            $patient->patientno,
                            $patientvisit?->arrival_date ?? "",
                            $patientvisit?->departure_date ?? "",
                            $patientvisit?->status ?? "",
                            $patientvisit?->visitno ?? ""
                        ]);
                    }

                    fclose($file);
                };

                return response()->stream($callback, 200, $headers);
            }

            $records = $this->patientService->getAllRecordFiltered(
                $search,
                $paginate,
                $perPage,
                $from,
                $to,
                $export,
                $gender,
                $status,
                $patient_type
            );

            if ($records->isEmpty()) {
                DB::connection('tenant')->commit();
                return JsonResponser::send(false, 'Record(s) not found.', null, 200);
            }

            $records->load([
                'service',
                'visits_recent',
            ]);

            $summary = $this->patientService->getRecordStats();

            DB::connection('tenant')->commit();

            return JsonResponser::send(false, 'Record(s) found successfully.', [
                'records' => collect($records),
                'summary' => $summary,
            ], 200);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
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

    public function exportPatients(Request $request, string $format)
    {
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $patients = $this->patientService->getExportData($search, $startDate, $endDate);

        if (empty($patients)) {
            return JsonResponser::send(true, 'No records found for export.', null, 200);
        }

        switch (strtolower($format)) {
            case 'csv':
                return ExportHelper::streamCsv($patients, null, 'patients_export.csv');

            case 'pdf':
                return ExportHelper::downloadPdf($patients, 'patients_export.pdf');

            default:
                return JsonResponser::send(true, 'Invalid export format.', null, 400);
        }
    }

    public function allVisitRecords(Request $request)
    {
        try {
            DB::connection('tenant');

            $search   = $request->input('search');
            $paginate = filter_var($request->input('paginate'), FILTER_VALIDATE_BOOLEAN);
            $perPage  = $request->input('perPage', 50);
            $export   = $request->input('export');

            $filters = [
                'stage'     => $request->input('stage'),
                'status'    => $request->input('status'),
                'date_from' => $request->input('date_from'),
                'date_to'   => $request->input('date_to'),
            ];

            $visits = $this->patientVisitService->getAllFiltered($search, $paginate, $perPage, $filters);

            if ($export === 'csv') {
                return Excel::download(new PatientVisitExport($visits), 'patient_visits.csv');
            }

            if ($export === 'pdf') {
                $pdf = Pdf::loadView('exports.patient_visits_pdf', ['visits' => $visits]);
                return $pdf->download('patient_visits.pdf');
            }

            if ($visits->isEmpty()) {
                return JsonResponser::send(false, 'No patient visit records found.', [], 200);
            }

            return JsonResponser::send(false, 'Patient Visit Records Fetched Successfully.', [
                'visits' => $visits,
            ], 200);
        } catch (\Throwable $e) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $e);
        }
    }


    public function patientVisitRecords(Request $request, int $patientId)
    {
        try {
            DB::connection('tenant');

            $search = $request->input('search');
            $filter = $request->input('filter');
            $paginate = filter_var($request->input('paginate', false), FILTER_VALIDATE_BOOLEAN);
            $perPage = (int) $request->input('perPage', 20);
            $export = $request->input('export');

            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $visitRecords = $this->patientVisitService->getVisitRecordsForPatient(
                $patientId,
                $search,
                $filter,
                $paginate,
                $perPage,
                $export
            );
            $dataForExport = $visitRecords instanceof \Illuminate\Contracts\Pagination\Paginator
                ? $visitRecords->items()
                : ($visitRecords instanceof \Illuminate\Support\Collection ? $visitRecords->toArray() : (array)$visitRecords);

            $filename = "patient_{$patientId}_visits_" . date('Ymd_His') . '.' . $export;

            if ($export === 'csv') {
                return ExportHelper::streamCsv($dataForExport, null, $filename);
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($dataForExport, $filename);
            }


            return JsonResponser::send(false, 'Patient visit records fetched successfully.', $visitRecords, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function patientVisitDetailWithBilling(Request $request, int $patientId, int $visitId)
    {
        try {
            DB::connection('tenant');

            $currentUser = Auth::user();
            // $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();

            if (is_null($user)) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $visitDetail = $this->patientVisitService->getVisitDetailWithBilling($patientId, $visitId);

            if (is_null($visitDetail)) {
                return JsonResponser::send(true, 'Visit record not found for this patient.', null, 200);
            }

            return JsonResponser::send(false, 'Patient visit detail with billing fetched successfully.', $visitDetail, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
