<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PatientInfomationRequest;
use App\Responser\JsonResponser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use App\Services\Revamp\PatientService;
use Azeemade\BulkUpload\Services\BulkUploadService;

class RecordManagementController extends Controller
{

    protected PatientService $patientService;

    public function __construct(
        PatientService $patientService,
    ) {
        $this->patientService = $patientService;
    }

    public function index(Request $request)
    {

        try {
            $overview = $this->patientService->overview($request);

            $stats = $this->patientService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];

            if (isset($request->export)) {
                $format = $request->export;
                return $this->patientService->export($overview, $format);
            }
            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function store(PatientInfomationRequest $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $tenantId = $request->header('X-Tenant-ID');
            $patientExists = Patient::where('tenant_id', $tenantId)->where('firstname', $request->firstname)->where('lastname', $request->lastname)->first();
            if ($patientExists) {
                return JsonResponser::send(true, 'A patient with the same firstname and lastname already exists.', null, 422);
            }

            //validate if Card number exists already
            if (!empty($request->cardno)) {
                $cardNoExists = Patient::where('tenant_id', $tenantId)
                    ->where('cardno', $request->cardno)
                    ->first();

                if ($cardNoExists) {
                    return JsonResponser::send(true, 'Card Number already exists.', null, 422);
                }
            }

            $patient = $this->patientService->create($request);
            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $patient->id,
                'action' => 'Create',
                'action_type' => "Models\Patient",
                'log_name' => "Patient details created successfully",
                'description' => "{$currentUser['fullname']} created patient details successfully",
                'module_accessed' => ListModuleEnums::Records

            ];
            GeneralHelper::storeAuditLog($dataToLog);

            // Create notification
            $tenant = $currentUser->currentTenant->first();
            $notificationData = [
                'user_id' => $currentUser->id,
                'tenant_domain' => $tenant->domain,
                'title' => 'Triage Required',
                'message' => "A newly onboarded patient has been added to the queue and is awaiting immediate clinical attention.
                                As the assigned nurse, it is your responsibility to initiate the Triage Assessment Workflow without delay to ensure timely and accurate care delivery.
                                Please proceed to begin the triage process now.",
                'role' => 'Nurse',
            ];
            Notification::create($notificationData);

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
            $patientExists = Patient::find($id);
            if (!$patientExists) {
                return JsonResponser::send(true, 'Patient Record not found.', null, 422);
            }

            $updatePatientDetails = $this->patientService->update($request, $id);

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $updatePatientDetails->id,
                'action' => 'Update',
                'action_type' => "Models\Patient",
                'log_name' => "Patient details updated successfully",
                'description' => "{$currentUser['fullname']} updated patient details successfully",
                'module_accessed' => ListModuleEnums::Records
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Patient details updated successfully', $updatePatientDetails, 200);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function show($id)
    {
        try {

            $patientExists = Patient::with(['nextOfKin', 'emergencyContact'])->find($id);
            if (!$patientExists) {
                return JsonResponser::send(true, 'Patient Record not found.', null, 422);
            }
            $patientExists->visit_date = $patientExists->visits_recent ? Carbon::parse($patientExists->visits_recent->arrival_date) : null;
            return JsonResponser::send(false, 'Record retrieved successfully.', $patientExists, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }

    public function fetchPatientDocuments(Request $request)
    {
        try {
            $patientExists = Patient::find($request->id);
            if (!$patientExists) {
                return JsonResponser::send(true, 'Patient Record not found.', null, 422);
            }

            $documents = $this->patientService->fetchDocuments($patientExists, $request);

            return JsonResponser::send(false, 'Documents retrieved successfully.', ['documents' => $documents], 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }

    public function uploadPatientDocuments(Request $request, $id)
    {
        $request->validate([
            'document_type' => 'required|string|max:255',
            'document_title' => 'required|string|max:255',
            'document_date' => 'nullable|date',
            'file' => 'required_without:document|file|max:10240',
            'document' => 'required_without:file|string',
        ]);

        try {
            $patientExists = Patient::find($id);
            if (!$patientExists) {
                return JsonResponser::send(true, 'Patient Record not found.', null, 422);
            }

            $uploadedFiles = $this->patientService->uploadDocuments($request, $patientExists);

            return JsonResponser::send(false, 'Documents uploaded successfully.', $uploadedFiles, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();
            $patientExists = Patient::find($id);
            if (!$patientExists) {
                return JsonResponser::send(true, 'Patient Record not found.', null, 422);
            }
            $this->patientService->delete($patientExists);
            DB::commit();
            return JsonResponser::send(false, 'Record retrieved successfully.', $patientExists, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }

    public function patientVisitRecords(Request $request)
    {

        try {
            $overview = $this->patientService->patientVisitOverview($request);

            $records = [
                'data' => $overview
            ];

            if ($request->export) {
                $format = $request->export;
                return $this->patientService->patientVisitExport($overview, $format);
            }
            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function initiateVisit(Request $request)
    {

        try {

            $request->validate([
                'patient_id' => 'required',
                'service_id' => 'required',
                // 'stage' => 'required|string'
            ]);
            DB::connection('tenant');

            $currentUser = Auth::user();

            $patientExists = Patient::find($request->patient_id);
            if (!$patientExists) {
                return JsonResponser::send(true, 'Patient Record not found.', null, 422);
            }

            // Check if the patient already has a visit today
            $patientVisit = PatientVisit::where('patient_id', $request->patient_id)
                // ->where('service_id', $request->service_id)
                ->where('status', '!=', PatientVisitStatusEnums::COMPLETED->value)
                // ->whereDate('created_at', Carbon::today())
                ->latest('created_at')
                ->first();

            if ($patientVisit) {
                if ($request->status === PatientVisitStatusEnums::COMPLETED->value) {
                    // End the current ongoing visit
                    $patientVisit->update([
                        'status'         => PatientVisitStatusEnums::COMPLETED->value,
                        'departure_date' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);
                } else {
                    // Patient still has an ongoing visit
                    return JsonResponser::send(false, 'A visit is already ongoing for this patient.', null, 422);
                }
            }
            $visit = $this->patientService->initiateVisit($request);

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $visit->id,
                'action' => 'Create',
                'action_type' => "Models\PatientVisit",
                'log_name' => "Patient visit created successfully",
                'description' => "{$currentUser['fullname']} created patient visit successfully",
                'module_accessed' => ListModuleEnums::Records

            ];
            GeneralHelper::storeAuditLog($dataToLog);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Visit created successfully.', ['visitRecord' => $visit], 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function showVisit($id)
    {
        try {

            $patientVisit = PatientVisit::with('patient', 'service', 'patientBilling', 'consultation')->find($id);
            if (!$patientVisit) {
                return JsonResponser::send(true, 'Patient visit not found.', null, 422);
            }

            if ($patientVisit->consultation && $patientVisit->consultation->consulted_by) {
                $consultedUser = User::on('landlord')
                    ->select('id', 'first_name', 'last_name', 'email')
                    ->find($patientVisit->consultation->consulted_by);

                $patientVisit->consultation->setAttribute('consultedBy', $consultedUser);
            } elseif ($patientVisit->consultation) {
                $patientVisit->consultation->setAttribute('consultedBy', null);
            } else {
                $patientVisit->setAttribute('consultation', null);
            }

            return JsonResponser::send(false, 'Record retrieved successfully.', $patientVisit, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }

    public function bulkUpload(Request $request, BulkUploadService $service)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $currentUser = Auth::user();
        $tenantId    = $request->header('X-Tenant-ID');

        $metadata = [
            'created_by' => $currentUser->id,
            'tenant_id'  => $tenantId,
            'source'     => 'api',
        ];

        DB::connection('landlord')->beginTransaction();
        try {
            $batch = $service->handle('patient', $request->file('file'), $metadata);
            DB::connection('landlord')->commit();
            return JsonResponser::send(false, 'Bulk upload started successfully', $batch, 202);
        } catch (\Exception $e) {
            DB::connection('landlord')->rollback();
            return JsonResponser::send(true, 'Bulk upload failed', [], 500, $e);
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
}
