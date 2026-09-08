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
use App\Models\Tenant;
use App\Models\User;
use App\Services\Patient\PatientAccountService;
use App\Services\Revamp\PatientService;
use Azeemade\BulkUpload\Services\BulkUploadService;
use Throwable;

class RecordManagementController extends Controller
{

    protected PatientService $patientService;
    protected PatientAccountService $patientAccountService;

    public function __construct(
        PatientService $patientService,
        PatientAccountService $patientAccountService,
    ) {
        $this->patientService = $patientService;
        $this->patientAccountService = $patientAccountService;
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
            // The patient's app account lives on the landlord database, so both
            // connections have to succeed or fail together.
            DB::connection('landlord')->beginTransaction();

            $currentUser = Auth::user();
            $tenantId = $request->header('X-Tenant-ID');
            $tenant = Tenant::where('uuid', $tenantId)->first();

            if (!$tenant) {
                $this->rollbackPatientRegistration();
                return JsonResponser::send(true, 'Invalid tenant provided.', null, 400);
            }

            $patientExists = Patient::where('tenant_id', $tenantId)->where('firstname', $request->firstname)->where('lastname', $request->lastname)->first();
            if ($patientExists) {
                $this->rollbackPatientRegistration();
                return JsonResponser::send(true, 'A patient with the same firstname and lastname already exists.', null, 422);
            }

            //validate if Card number exists already
            if (!empty($request->cardno)) {
                $cardNoExists = Patient::where('tenant_id', $tenantId)
                    ->where('cardno', $request->cardno)
                    ->first();

                if ($cardNoExists) {
                    $this->rollbackPatientRegistration();
                    return JsonResponser::send(true, 'Card Number already exists.', null, 422);
                }
            }

            $patient = $this->patientService->create($request);

            // Give the patient the landlord account they sign into the patient
            // mobile app with. The invitation mail waits until both connections
            // have committed, so a rolled back registration never mails out
            // credentials for a patient who does not exist.
            $account = $this->patientAccountService->provision($patient, $tenant);

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

            DB::connection('landlord')->commit();
            DB::connection('tenant')->commit();

            $invitationSent = $this->patientAccountService->sendInvitation(
                $account['user'],
                $patient,
                $tenant,
                $account['password']
            );

            return JsonResponser::send(false, 'Patient details created successfully', [
                'patient' => $patient->fresh(),
                'app_account' => [
                    'user_id'          => $account['user']->id,
                    'email'            => $account['user']->email,
                    'is_new_account'   => $account['is_new'],
                    'invitation_sent'  => $invitationSent,
                ],
            ], 201);
        } catch (\Throwable $th) {
            $this->rollbackPatientRegistration();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    /**
     * Re-send a patient's app invitation with a fresh temporary password.
     *
     * For the patient who never received the first mail, or whose verification
     * expired after they lost it. Pass reset_password to also overwrite a
     * password the patient has already chosen for themselves.
     */
    public function resendInvitation(Request $request, $id)
    {
        DB::connection('landlord')->beginTransaction();

        try {
            $currentUser = Auth::user();
            $tenantId = $request->header('X-Tenant-ID');
            $tenant = Tenant::where('uuid', $tenantId)->first();

            if (!$tenant) {
                DB::connection('landlord')->rollBack();
                return JsonResponser::send(true, 'Invalid tenant provided.', null, 400);
            }

            $patient = Patient::find($id);

            if (!$patient) {
                DB::connection('landlord')->rollBack();
                return JsonResponser::send(true, 'Patient record not found.', null, 404);
            }

            if (empty($patient->email)) {
                DB::connection('landlord')->rollBack();
                return JsonResponser::send(true, 'This patient has no email address to send an invitation to.', null, 422);
            }

            $account = $this->patientAccountService->resendInvitation(
                $patient,
                $tenant,
                $request->boolean('reset_password')
            );

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $patient->id,
                'action' => 'Update',
                'action_type' => "Models\Patient",
                'log_name' => "Patient app invitation resent",
                'description' => "{$currentUser['fullname']} resent the patient app invitation to {$patient->email}",
                'module_accessed' => ListModuleEnums::Records,
            ];
            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('landlord')->commit();

            // Mailed only once the new password is safely stored, so a failed
            // send never leaves the patient holding credentials we discarded.
            $invitationSent = $this->patientAccountService->sendInvitation(
                $account['user'],
                $patient,
                $tenant,
                $account['password']
            );

            return JsonResponser::send(false, 'Invitation resent successfully.', [
                'email' => $account['user']->email,
                'invitation_sent' => $invitationSent,
            ], 200);
        } catch (\RuntimeException $th) {
            DB::connection('landlord')->rollBack();
            return JsonResponser::send(true, $th->getMessage(), [], 409);
        } catch (\Throwable $th) {
            DB::connection('landlord')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    /**
     * Undo both halves of a patient registration.
     *
     * The patient lives on the tenant database and their app account on the
     * landlord one, so neither may be left behind without the other.
     */
    private function rollbackPatientRegistration(): void
    {
        DB::connection('landlord')->rollBack();
        DB::connection('tenant')->rollBack();
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

            // Attach the assigned consultant (from the latest visit, else the patient's
            // preferred doctor) so the triage screen can display it. Users live on landlord.
            $doctorId = $patientExists->visits_recent?->doctor_id ?? $patientExists->preferred_doctor_id;
            $consultant = $doctorId
                ? User::on('landlord')->select('id', 'first_name', 'last_name', 'email')->find($doctorId)
                : null;
            $patientExists->setAttribute('doctor_id', $doctorId);
            $patientExists->setAttribute('consultant', $consultant);

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
            $bundle = $this->patientService->fetchPatientDocumentsBundle($patientExists, $request);
            return JsonResponser::send(false, 'Documents retrieved successfully.', $bundle, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred.', 'Internal server error', 500, $th);
        }
    }

    public function showPatientDocument(Request $request, $id)
    {
        try {
            $recordType = $request->query('record_type', $request->query('type', 'patient_document'));

            if (in_array(strtolower((string) $recordType), ['patient_document', 'document', 'patient-doc'], true)) {
                $document = $this->patientService->showPatientDocument($id);
                return JsonResponser::send(false, 'Documents retrieved successfully.', ['documents' => $document], 200);
            }

            $record = $this->patientService->showPatientRecord($id, (string) $recordType);
            return JsonResponser::send(false, 'Documents retrieved successfully.', ['record' => $record], 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal server error', 500, $th);
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

    public function deletePatientDocument($id)
    {
        try {
            $documentDeleted = $this->patientService->deletePatientDocument($id);

            if (!$documentDeleted) {
                return JsonResponser::send(true, 'Document not found.', null, 422);
            }

            return JsonResponser::send(false, 'Document deleted successfully.', null, 200);
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
                'doctor_id'  => 'nullable',
                // 'stage' => 'required|string'
            ]);

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
                } elseif (\App\Helpers\VisitPolicy::closeStaleOutpatient($patientVisit)) {
                    // Prior-day outpatient visit — auto-closed; fall through to create a new visit for today.
                } else {
                    // Patient still has an ongoing (same-day or admitted) visit
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

            // Assigned consultant for this visit (landlord user).
            $visitConsultant = $patientVisit->doctor_id
                ? User::on('landlord')->select('id', 'first_name', 'last_name', 'email')->find($patientVisit->doctor_id)
                : null;
            $patientVisit->setAttribute('consultant', $visitConsultant);

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

    public function patientCareNotes(Request $request)
    {
        try {
            $careNotes = $this->patientService->getPatientCareNotes($request);

            $records = [
                'data' => $careNotes
            ];

            if ($request->export) {
                $format = $request->export;
                return $this->patientService->exportPatientCareNotes($careNotes, $format);
            }

            if (!$request->paginate) {
                $records = $careNotes;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function addPatientCareNotes(Request $request)
    {
        try {
            $careNote = $this->patientService->addPatientCareNote($request);

            return JsonResponser::send(false, 'Care note added successfully', $careNote);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function viewPatientCareNotes($id)
    {
        try {
            $records = $this->patientService->viewPatientCareNotes($id);
            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
    public function updatePatientCareNotes(Request $request, $id)
    {
        try {
            $records = $this->patientService->updatePatientCareNotes($request, $id);
            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
