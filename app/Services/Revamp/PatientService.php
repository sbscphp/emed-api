<?php

namespace App\Services\Revamp;

use App\Enums\AppointmentStatusEnums;
use App\Enums\GeneralEnums;
use App\Enums\ListModuleEnums;
use App\Enums\PatientVisitStageEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Models\Patient;
use App\Repositories\Patient\PatientInterface;
use App\Helpers\ExportHelper;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Models\Appointment;
use App\Models\BillingLog;
use App\Models\BillingLogDetail;
use App\Models\CareNote;
use App\Models\Consultation;
use App\Models\CounsellingDetail;
use App\Models\EmergencyContact;
use App\Models\Immunization;
use App\Models\Laboratory;
use App\Models\LaboratoryResult;
use App\Models\PatientDocument;
use App\Models\RadiologyResult;
use App\Models\NextOfKin;
use App\Models\User;
use App\Models\Radiology;
use App\Models\PatientVisit;
use App\Models\Service;
use App\Models\ServiceUnit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Models\Tenant;

/**
 * Class PatientService
 *
 * This class provides services related to Patient operations and acts as a
 * layer between the Controller and the PatientRepository.
 */
class PatientService
{
    /**
     * Patient constructor.
     *
     * @param PatientInterface $PatientInterface
     */
    public function __construct(PatientInterface $PatientInterface) {}

    /**
     * Retrieve all Patient.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function overview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $records = Patient::query()
            ->where('tenant_id', $tenantId)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('lastname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('cardno', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'alphabetically', function ($query) {
                $query->orderBy('firstname', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            })
            ->with('nextOfKin', 'emergencyContact', 'visits_recent');

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function stats($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }
        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');
        $query = Patient::query()->where('tenant_id', $tenantId);
        $total = (clone $query)->count();
        $patientLog = (clone $query)->count();
        $admitted = (clone $query)->where('status', GeneralEnums::ADMITTED->value)->count();
        $patientVisitToday = PatientVisit::where('tenant_id', $tenantId)->whereDate('created_at', now()->toDateString())->count();
        $consultantFollowUpPatient = Consultation::where('tenant_id', $tenantId)->where('schedule_a_follow_up', 1)->count();
        $hivFollowUpPatient = CounsellingDetail::where('tenant_id', $tenantId)->where('schedule_a_follow_up', 1)->count();
        $immunizationFollowUpPatient = Immunization::where('tenant_id', $tenantId)->where('schedule_a_follow_up', 1)->count();
        $followUpPatient = $consultantFollowUpPatient + $hivFollowUpPatient + $immunizationFollowUpPatient;

        return [
            'totalPatient' => $total,
            'admitted' => $admitted,
            'totalPatientVisitToday' => $patientVisitToday,
            'followUpPatient' => $followUpPatient,
            'patientLog' => $patientLog,
        ];
    }

    public function export($records, $format)
    {
        $exportData = $records->map(function ($patient) {
            return [
                'Patient Card'      => $patient->cardno,
                'First Name'      => $patient->firstname,
                'Last Name'       => $patient->lastname,
                'Patient No'       => $patient->patientno,
                'Email'           => $patient->email,
                'Phone Number'    => $patient->phoneno,
                'Patient Status'  => $patient->status,
                'Registered Date' => $patient->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patients_export.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = PDF::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patients_export.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    /**
     * Create a new Patient using the data provided.
     *
     * @param array $data
     * @return \App\Models\Patient
     */
    public function create($request)
    {
        try {

            $currentUser = Auth::user();

            $tenant = Tenant::current(); //Retrieve the current tenant
            $tenantDomain = $tenant ? $tenant->domain : 'emed'; // Current tenant domain name
            $tenantAcronym = $this->generateAcronym($tenantDomain); //Acronym for the hospital name()
            $tenantId = $request->header('X-Tenant-ID');
            // Create Patient
            $patient = Patient::create([
                'tenant_id' => $tenantId,
                'created_by' => $currentUser->id,
                'patientno' => 'EMED/' . GeneralHelper::generateUniqueRandomId($request->firstname) . '/' . GeneralHelper::generateUniqueRandomId($request->lastname) . '/' . $tenantAcronym,
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'dob' => $request->dob,
                'phoneno' => $request->phoneno,
                'age' => $request->age,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'email' => $request->email,
                'lga' => $request->lga,
                'stateoforigin' => $request->stateoforigin,
                'homeaddress' => $request->homeaddress,
                'occupation' => $request->occupation,
                'religion' => $request->religion,
                'tribe' => $request->tribe,
                'bloodgroup' => $request->bloodgroup,
                'cardno' => $request->cardno,
                'genotype' => $request->genotype,
                'referral' => $request->referral,
                'payer_type' => $request->payer_type,
                'nhis_number' => $request->nhis_number,
                'nhis_scheme' => $request->nhis_scheme,
                'hmo_name' => $request->hmo_name,
                'hmo_number' => $request->hmo_number,
                'principal_name' => $request->principal_name,
                'employee_id' => $request->employee_id,
                'company_name' => $request->company_name,
                'allergies' => $request->allergies,
            ]);

            // create next of kin
            $nextOfKin = NextOfKin::create([
                'patient_id' => $patient->id,
                'firstname' => $request->nokfirstname,
                'lastname' => $request->noklastname,
                'gender' => $request->nokgender,
                'phoneno' => $request->nokphoneno,
                'stateoforigin' => $request->nokstateoforigin,
                'lga' => $request->noklga,
                'homeaddress' => $request->nokhomeaddress,
                'relationship' => $request->nokrelationship,
            ]);

            if (isset($requestrequest->same_nok_emergency) && $request->same_nok_emergency == true) {
                // create emergency contact same as next of kin
                $emergencyyContact = EmergencyContact::create([
                    'patient_id' => $patient->id,
                    'firstname' => $request->nokfirstname,
                    'lastname' => $request->noklastname,
                    'gender' => $request->nokgender,
                    'phoneno' => $request->nokphoneno,
                    'stateoforigin' => $request->nokstateoforigin,
                    'lga' => $request->noklga,
                    'homeaddress' => $request->nokhomeaddress,
                    'relationship' => $request->nokrelationship,
                ]);
            } else {
                // create emergency contact
                $emergencyyContact = EmergencyContact::create([
                    'patient_id' => $patient->id,
                    'firstname' => $request->emgfirstname,
                    'lastname' => $request->emglastname,
                    'gender' => $request->emggender,
                    'phoneno' => $request->emgphoneno,
                    'stateoforigin' => $request->emgstateoforigin,
                    'lga' => $request->emglga,
                    'homeaddress' => $request->emghomeaddress,
                    'relationship' => $request->emgrelationship,
                ]);
            }

            return $patient;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Update an existing Patient with the provided data.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Patient
     */
    public function update($request, $id)
    {
        try {

            $currentUser = Auth::user();
            $patient = Patient::find($id);
            // Update Patient
            $patient->update([
                'updated_by' => $currentUser->id,
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'middlename' => $request->middlename,
                'phoneno' => $request->phoneno,
                'email' => $request->email,
                'dob' => $request->dob,
                "age" =>  $request->age,
                'gender' => $request->gender,
                'genotype' => $request->genotype,
                'bloodgroup' => $request->bloodgroup,
                'marital_status' => $request->marital_status,
                'tribe' => $request->tribe,
                'homeaddress' => $request->homeaddress,
                'cardno' => $request->cardno,
                'occupation' => $request->occupation,
                'stateoforigin' => $request->stateoforigin,
                'lga' => $request->lga,
                'referral' => $request->referral,
                'payer_type' => $request->payer_type,
                'nhis_number' => $request->nhis_number,
                'nhis_scheme' => $request->nhis_scheme,
                'hmo_name' => $request->hmo_name,
                'hmo_number' => $request->hmo_number,
                'principal_name' => $request->principal_name,
                'employee_id' => $request->employee_id,
                'company_name' => $request->company_name,
            ]);

            // Update next of kin
            $nextOfKin = NextOfKin::where('patient_id', $id)->first();
            $nextOfKin->update([
                'firstname' => $request->nokfirstname,
                'lastname' => $request->noklastname,
                'gender' => $request->nokgender,
                'phoneno' => $request->nokphoneno,
                'stateoforigin' => $request->nokstateoforigin,
                'lga' => $request->noklga,
                'homeaddress' => $request->nokhomeaddress,
                'relationship' => $request->nokrelationship,
            ]);

            // update emergency contact
            $emergencyyContact = EmergencyContact::where('patient_id', $id)->first();
            $emergencyyContact->update([
                'firstname' => $request->emgfirstname,
                'lastname' => $request->emglastname,
                'gender' => $request->emggender,
                'phoneno' => $request->emgphoneno,
                'stateoforigin' => $request->emgstateoforigin,
                'lga' => $request->emglga,
                'homeaddress' => $request->emghomeaddress,
                'relationship' => $request->emgrelationship,
            ]);

            return $patient->refresh();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * The record types that make up a patient's document list.
     *
     * Patient documents, laboratory results and radiology reports live in
     * three different tables but are presented to the record officer as a
     * single list, so every one of them is mapped to the same shape.
     */
    public const RECORD_TYPE_DOCUMENT = 'patient_document';
    public const RECORD_TYPE_LAB = 'lab_test';
    public const RECORD_TYPE_RADIOLOGY = 'radiology_test';

    /**
     * Aliases accepted from the client for each record type.
     */
    private const RECORD_TYPE_ALIASES = [
        self::RECORD_TYPE_DOCUMENT => ['patient_document', 'patient-document', 'document', 'documents', 'patient-doc', 'patient_doc'],
        self::RECORD_TYPE_LAB => ['lab_test', 'lab-test', 'lab', 'labs', 'laboratory', 'lab_result', 'lab-result', 'laboratory_result'],
        self::RECORD_TYPE_RADIOLOGY => ['radiology_test', 'radiology-test', 'radiology', 'radiology_result', 'radiology-result', 'radio_result', 'scan', 'imaging'],
    ];

    /**
     * Resolve a client supplied record type to one of the canonical types.
     */
    private function normalizeRecordType($recordType): ?string
    {
        $recordType = strtolower(trim((string) $recordType));
        if ($recordType === '') {
            return null;
        }

        foreach (self::RECORD_TYPE_ALIASES as $canonical => $aliases) {
            if (in_array($recordType, $aliases, true)) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * Split an identifier such as "lab_test-21" into its record type and id.
     *
     * Plain numeric ids are still accepted so older clients that only know
     * about patient documents keep working.
     *
     * @return array{0: string|null, 1: string} [record type, id]
     */
    private function parseRecordIdentifier($identifier): array
    {
        $identifier = trim((string) $identifier);

        if (preg_match('/^([A-Za-z][A-Za-z_-]*)[-:](\d+)$/', $identifier, $matches)) {
            $type = $this->normalizeRecordType($matches[1]);
            if ($type) {
                return [$type, $matches[2]];
            }
        }

        return [null, $identifier];
    }

    /**
     * Load the display names of the given landlord users in one query.
     *
     * @param  array<int, int|string|null> $userIds
     * @return array<int, string>
     */
    private function resolveUserNames(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (empty($userIds)) {
            return [];
        }

        return User::on('landlord')
            ->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'last_name', 'fullname', 'email'])
            ->mapWithKeys(function ($user) {
                $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                $name = $name !== '' ? $name : $user->fullname;
                return [$user->id => $name ?: $user->email];
            })
            ->all();
    }

    /**
     * The shape every record in the patient document list is returned in.
     *
     * Uploaded documents, lab results and radiology reports all carry the
     * same keys - the ones that do not apply to a type stay null - so the
     * client can render one table without special casing each type.
     */
    private function recordDefaults(): array
    {
        return [
            'record_id' => null,
            'record_type' => null,
            'record_type_label' => null,
            'id' => null,
            'patient_id' => null,
            'visit_id' => null,
            'consultation_id' => null,
            'test_id' => null,
            'document_title' => null,
            'document_type' => null,
            'document_date' => null,
            'uploaded_by_id' => null,
            'uploaded_by' => null,
            'uploaded_on' => null,
            'status' => null,
            'department' => null,
            'specimen_type' => null,
            'notes' => null,
            'results_count' => 0,
            'file_url' => null,
            'file_name' => null,
            'has_file' => false,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * Map an uploaded patient document onto the shared record shape.
     */
    private function formatDocumentRecord($document): array
    {
        return array_merge($this->recordDefaults(), [
            'record_id' => self::RECORD_TYPE_DOCUMENT . '-' . $document->id,
            'record_type' => self::RECORD_TYPE_DOCUMENT,
            'record_type_label' => 'Patient Document',
            'id' => $document->id,
            'patient_id' => $document->patient_id,
            'visit_id' => null,
            'consultation_id' => null,
            'document_title' => $document->document_title,
            'document_type' => $document->document_type,
            'document_date' => optional($document->document_date)->toDateString()
                ?: optional($document->created_at)->toDateString(),
            'uploaded_by_id' => $document->uploaded_by,
            'uploaded_by' => $document->uploaded_by_name,
            'uploaded_on' => optional($document->created_at)->toDateTimeString(),
            'status' => 'Available',
            'file_url' => $document->file_url,
            'file_name' => $document->file_name,
            'has_file' => !empty($document->file_url),
            'created_at' => optional($document->created_at)->toDateTimeString(),
            'updated_at' => optional($document->updated_at)->toDateTimeString(),
        ]);
    }

    /**
     * Map a laboratory test onto the shared record shape.
     *
     * @param  array<int, string> $userNames
     */
    private function formatLabRecord($test, array $userNames = []): array
    {
        $results = $test->relationLoaded('results') ? $test->results : $test->results()->get();
        $latestResult = $results->sortByDesc('updated_at')->first();
        $uploadedById = $test->user_id ?: optional($test->consultation)->consulted_by;

        return array_merge($this->recordDefaults(), [
            'record_id' => self::RECORD_TYPE_LAB . '-' . $test->id,
            'record_type' => self::RECORD_TYPE_LAB,
            'record_type_label' => 'Lab Result',
            'id' => $test->id,
            'patient_id' => $test->patient_id,
            'visit_id' => $test->visit_id,
            'consultation_id' => $test->consultation_id,
            'test_id' => $test->test_id,
            'document_title' => $test->test_name ?: (optional($latestResult)->test ?: 'Lab Result'),
            'document_type' => 'Lab Result',
            'document_date' => optional(optional($latestResult)->created_at ?: $test->created_at)->toDateString(),
            'uploaded_by_id' => $uploadedById,
            'uploaded_by' => $uploadedById ? ($userNames[$uploadedById] ?? null) : null,
            'uploaded_on' => optional($test->created_at)->toDateTimeString(),
            'status' => $test->status,
            'department' => $test->department,
            'specimen_type' => $test->specimen_type,
            'notes' => $test->notes,
            'results_count' => $results->count(),
            'created_at' => optional($test->created_at)->toDateTimeString(),
            'updated_at' => optional($test->updated_at)->toDateTimeString(),
        ]);
    }

    /**
     * Map a radiology test onto the shared record shape.
     *
     * @param  array<int, string> $userNames
     */
    private function formatRadiologyRecord($test, array $userNames = []): array
    {
        $results = $test->relationLoaded('results') ? $test->results : $test->results()->get();
        $latestResult = $results->sortByDesc('updated_at')->first();
        $uploadedById = optional($latestResult)->user_id ?: ($test->user_id ?: optional($test->consultation)->consulted_by);
        $resultImage = optional($latestResult)->result_img;

        return array_merge($this->recordDefaults(), [
            'record_id' => self::RECORD_TYPE_RADIOLOGY . '-' . $test->id,
            'record_type' => self::RECORD_TYPE_RADIOLOGY,
            'record_type_label' => 'Scan/Imaging',
            'id' => $test->id,
            'patient_id' => $test->patient_id,
            'visit_id' => $test->visit_id,
            'consultation_id' => $test->consultation_id,
            'test_id' => $test->test_id,
            'document_title' => $test->test_name ?: (optional($latestResult)->examination_type ?: 'Radiology Result'),
            'document_type' => 'Scan/Imaging',
            'document_date' => optional(optional($latestResult)->created_at ?: $test->created_at)->toDateString(),
            'uploaded_by_id' => $uploadedById,
            'uploaded_by' => $uploadedById ? ($userNames[$uploadedById] ?? null) : null,
            'uploaded_on' => optional($test->created_at)->toDateTimeString(),
            'status' => $test->status,
            'department' => $test->department,
            'results_count' => $results->count(),
            'file_url' => $resultImage,
            'file_name' => $resultImage ? $this->fileNameFromUrl($resultImage) : null,
            'has_file' => !empty($resultImage),
            'created_at' => optional($test->created_at)->toDateTimeString(),
            'updated_at' => optional($test->updated_at)->toDateTimeString(),
        ]);
    }

    /**
     * The file name part of a stored file URL.
     */
    private function fileNameFromUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return $path ? basename($path) : null;
    }

    /**
     * Documents uploaded against a patient.
     *
     * Mapped to the same record shape as the merged document list so both can
     * be rendered by the same table.
     */
    public function fetchDocuments($patientExists, $request)
    {
        $patient = $patientExists instanceof Patient ? $patientExists : Patient::find($patientExists->id);
        if (!$patient) {
            return [];
        }

        $request = $request ?? request();

        $query = PatientDocument::where('patient_id', $patient->id)
            ->when($request->filled('search_param'), function ($query) use ($request) {
                $search = '%' . $request->search_param . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('document_title', 'LIKE', $search)
                        ->orWhere('document_type', 'LIKE', $search)
                        ->orWhere('uploaded_by_name', 'LIKE', $search);
                });
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($request->filled('period'), function ($query) use ($request) {
                $customDate = [];
                if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
                    $customDate = [$request->start_date, $request->end_date];
                }
                $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
                if ($dateFilter) {
                    $query->whereBetween('created_at', $dateFilter);
                }
            })
            ->orderBy('created_at', 'DESC');

        $mapDocument = function ($document) {
            return $this->formatDocumentRecord($document);
        };

        if ($request->boolean('paginate', false)) {
            $documents = $query->paginate($request->limit ?? 15);
            $documents->getCollection()->transform($mapDocument);
            return $documents;
        }

        return $query->get()->map($mapDocument)->all();
    }

    /**
     * Every document a patient has - uploaded documents, laboratory results
     * and radiology reports - merged into a single, uniformly shaped list.
     *
     * Supported query parameters:
     *  - paginate, page, limit       paginate the merged list
     *  - record_type                 limit the list to one of the three types
     *  - search_param                free text search
     *  - period, start_date, end_date  date filters
     *  - include_pending             also list tests that have no result yet
     */
    public function fetchPatientDocumentsBundle($patientExists, $request): array
    {
        $patient = $patientExists instanceof Patient ? $patientExists : Patient::find($patientExists->id);
        if (!$patient) {
            return [
                'data' => [],
                'records' => [],
                'documents' => [],
                'lab_tests' => [],
                'radiology_tests' => [],
                'meta' => null,
                'counts' => [
                    'all' => 0,
                    self::RECORD_TYPE_DOCUMENT => 0,
                    self::RECORD_TYPE_LAB => 0,
                    self::RECORD_TYPE_RADIOLOGY => 0,
                ],
            ];
        }

        $request = $request ?? request();
        $recordTypeFilter = $this->normalizeRecordType($request->query('record_type'));
        $includePending = $request->boolean('include_pending', false);

        $customDate = [];
        if ($request->filled('period') && $request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }
        $dateFilter = $request->filled('period') ? GeneralHelper::dateFilter($request->period, $customDate) : null;

        // Pagination is applied to the merged list, so each per type list is
        // always fetched in full.
        $documents = [];
        if (!$recordTypeFilter || $recordTypeFilter === self::RECORD_TYPE_DOCUMENT) {
            $documentsRequest = $request->duplicate();
            $documentsRequest->query->remove('paginate');
            $documentsRequest->request->remove('paginate');
            $documents = $this->fetchDocuments($patient, $documentsRequest);
            $documents = is_array($documents) ? $documents : collect($documents)->all();
        }

        $labRecords = collect();
        if (!$recordTypeFilter || $recordTypeFilter === self::RECORD_TYPE_LAB) {
            $labRecords = Laboratory::query()
                ->with(['results', 'consultation:id,consulted_by'])
                ->where('patient_id', $patient->id)
                ->when(!$includePending, function ($query) {
                    $query->where(function ($q) {
                        $q->where('status', 'Ready')->orWhereHas('results');
                    });
                })
                ->when($request->filled('search_param'), function ($query) use ($request) {
                    $search = '%' . $request->search_param . '%';
                    $query->where(function ($q) use ($search) {
                        $q->where('test_name', 'LIKE', $search)
                            ->orWhere('department', 'LIKE', $search)
                            ->orWhere('specimen_type', 'LIKE', $search)
                            ->orWhere('notes', 'LIKE', $search)
                            ->orWhereHas('results', function ($r) use ($search) {
                                $r->where('test', 'LIKE', $search)
                                    ->orWhere('result', 'LIKE', $search);
                            });
                    });
                })
                ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                    $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
                })
                ->when($dateFilter, function ($query) use ($dateFilter) {
                    $query->whereBetween('created_at', $dateFilter);
                })
                ->orderBy('created_at', 'DESC')
                ->get();
        }

        $radiologyRecords = collect();
        if (!$recordTypeFilter || $recordTypeFilter === self::RECORD_TYPE_RADIOLOGY) {
            $radiologyRecords = Radiology::query()
                ->with(['results', 'consultation:id,consulted_by'])
                ->where('patient_id', $patient->id)
                ->when(!$includePending, function ($query) {
                    $query->where(function ($q) {
                        $q->where('status', 'Ready')->orWhereHas('results');
                    });
                })
                ->when($request->filled('search_param'), function ($query) use ($request) {
                    $search = '%' . $request->search_param . '%';
                    $query->where(function ($q) use ($search) {
                        $q->where('test_name', 'LIKE', $search)
                            ->orWhere('department', 'LIKE', $search)
                            ->orWhereHas('results', function ($r) use ($search) {
                                $r->where('examination_type', 'LIKE', $search)
                                    ->orWhere('findings', 'LIKE', $search);
                            });
                    });
                })
                ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                    $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
                })
                ->when($dateFilter, function ($query) use ($dateFilter) {
                    $query->whereBetween('created_at', $dateFilter);
                })
                ->orderBy('created_at', 'DESC')
                ->get();
        }

        // Users live on the landlord connection, so their names are resolved
        // once for the whole list instead of once per record.
        $userIds = $labRecords
            ->map(function ($test) {
                return $test->user_id ?: optional($test->consultation)->consulted_by;
            })
            ->toBase()
            ->merge($radiologyRecords->map(function ($test) {
                $latestResult = $test->results->sortByDesc('updated_at')->first();
                return optional($latestResult)->user_id ?: ($test->user_id ?: optional($test->consultation)->consulted_by);
            })->toBase())
            ->all();
        $userNames = $this->resolveUserNames($userIds);

        $labTests = $labRecords->map(function ($test) use ($userNames) {
            return $this->formatLabRecord($test, $userNames);
        })->all();

        $radiologyTests = $radiologyRecords->map(function ($test) use ($userNames) {
            return $this->formatRadiologyRecord($test, $userNames);
        })->all();

        $records = array_merge($documents, $labTests, $radiologyTests);
        usort($records, function ($a, $b) {
            return strtotime($b['created_at'] ?? '1970-01-01 00:00:00') <=> strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
        });

        $counts = [
            'all' => count($records),
            self::RECORD_TYPE_DOCUMENT => count($documents),
            self::RECORD_TYPE_LAB => count($labTests),
            self::RECORD_TYPE_RADIOLOGY => count($radiologyTests),
        ];

        $meta = null;
        $paginated = $records;

        if ($request->boolean('paginate', false)) {
            $page = (int) $request->query('page', 1);
            $page = $page > 0 ? $page : 1;
            $perPage = (int) ($request->limit ?? $request->per_page ?? 15);
            $perPage = $perPage > 0 ? $perPage : 15;

            $total = count($records);
            $items = array_slice($records, ($page - 1) * $perPage, $perPage);

            $paginator = new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            $paginated = $paginator;
            $records = $items;
            $meta = [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ];
        }

        return [
            'data' => $records,
            'records' => $paginated,
            'documents' => $documents,
            'lab_tests' => $labTests,
            'radiology_tests' => $radiologyTests,
            'meta' => $meta,
            'counts' => $counts,
        ];
    }

    /**
     * A single uploaded patient document.
     */
    public function showPatientDocument($id): array
    {
        [$type, $recordId] = $this->parseRecordIdentifier($id);
        if ($type && $type !== self::RECORD_TYPE_DOCUMENT) {
            return $this->showPatientRecord($id, $type);
        }

        $document = PatientDocument::find($recordId);
        if (!$document) {
            throw new ModelNotFoundException('Document not found.');
        }

        return array_merge($this->formatDocumentRecord($document), [
            'results' => [],
            'files' => $document->file_url ? [[
                'url' => $document->file_url,
                'name' => $document->file_name ?: $this->fileNameFromUrl($document->file_url),
            ]] : [],
        ]);
    }

    /**
     * A single record from the patient document list.
     *
     * $id accepts either the "<record_type>-<id>" identifier returned by the
     * list endpoint or a plain id. With a plain id and no record type each
     * table is searched in turn, so older clients that only pass a numeric id
     * keep working.
     */
    public function showPatientRecord($id, ?string $recordType = null): array
    {
        [$parsedType, $recordId] = $this->parseRecordIdentifier($id);
        $type = $parsedType ?: $this->normalizeRecordType($recordType);

        if ($type === self::RECORD_TYPE_DOCUMENT) {
            return $this->showPatientDocument($recordId);
        }

        if ($type === self::RECORD_TYPE_LAB) {
            return $this->showLabRecord($recordId);
        }

        if ($type === self::RECORD_TYPE_RADIOLOGY) {
            return $this->showRadiologyRecord($recordId);
        }

        if (trim((string) $recordType) !== '') {
            throw new \InvalidArgumentException('Unsupported record type.');
        }

        foreach ([self::RECORD_TYPE_DOCUMENT, self::RECORD_TYPE_LAB, self::RECORD_TYPE_RADIOLOGY] as $candidate) {
            try {
                return $this->showPatientRecord($recordId, $candidate);
            } catch (ModelNotFoundException $e) {
                continue;
            }
        }

        throw new ModelNotFoundException('Record not found.');
    }

    /**
     * A single laboratory test together with its results.
     */
    private function showLabRecord($id): array
    {
        $test = Laboratory::with(['results.parameter', 'consultation:id,consulted_by'])->find($id);
        if (!$test) {
            throw new ModelNotFoundException('Lab test not found.');
        }

        $userNames = $this->resolveUserNames([$test->user_id, optional($test->consultation)->consulted_by]);

        $results = $test->results
            ->sortBy([['display_order', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function ($result) {
                return [
                    'id' => $result->id,
                    'lab_parameter_id' => $result->lab_parameter_id ?? null,
                    'test' => $result->test,
                    'result' => $result->result,
                    'unit' => $result->unit ?? null,
                    'reference_range' => $result->reference_range,
                    'flag' => $result->flag ?? null,
                    'display_order' => $result->display_order ?? 0,
                    'status' => $result->status,
                    'created_at' => optional($result->created_at)->toDateTimeString(),
                    'updated_at' => optional($result->updated_at)->toDateTimeString(),
                ];
            })->all();

        return array_merge($this->formatLabRecord($test, $userNames), [
            'results' => $results,
            'files' => [],
        ]);
    }

    /**
     * A single radiology test together with its report and images.
     */
    private function showRadiologyRecord($id): array
    {
        $test = Radiology::with(['results', 'consultation:id,consulted_by'])->find($id);
        if (!$test) {
            throw new ModelNotFoundException('Radiology test not found.');
        }

        $latestResult = $test->results->sortByDesc('updated_at')->first();
        $userNames = $this->resolveUserNames([
            optional($latestResult)->user_id,
            $test->user_id,
            optional($test->consultation)->consulted_by,
        ]);

        $results = $test->results
            ->values()
            ->map(function ($result) {
                return [
                    'id' => $result->id,
                    'radiology_id' => $result->radiology_id,
                    'patient_id' => $result->patient_id,
                    'examination_type' => $result->examination_type,
                    'clinical_indication' => $result->clinical_indication,
                    'technique' => $result->technique,
                    'findings' => $result->findings,
                    'result_img' => $result->result_img,
                    'status' => $result->status,
                    'created_at' => optional($result->created_at)->toDateTimeString(),
                    'updated_at' => optional($result->updated_at)->toDateTimeString(),
                ];
            })->all();

        $files = collect($results)
            ->pluck('result_img')
            ->filter()
            ->unique()
            ->values()
            ->map(function ($url) {
                return [
                    'url' => $url,
                    'name' => $this->fileNameFromUrl($url),
                ];
            })->all();

        return array_merge($this->formatRadiologyRecord($test, $userNames), [
            'results' => $results,
            'files' => $files,
        ]);
    }

    public function uploadDocuments($request, $patient)
    {
        $currentUser = Auth::user();
        $tenantId = $request->header('X-Tenant-ID');

        $fileUrl = null;
        if ($request->hasFile('file')) {
            $fileUrl = FileUploadHelper::singleBinaryFileUpload($request->file('file'), 'patient_documents');
        } elseif ($request->filled('document')) {
            $fileUrl = FileUploadHelper::singleStringFileUpload($request->document, 'patient_documents');
        } elseif ($request->filled('file_url')) {
            $fileUrl = $request->file_url;
        }

        if (!$fileUrl) {
            throw new \Exception('No document file was provided.');
        }

        $uploadedDocument = PatientDocument::create([
            'tenant_id' => $tenantId,
            'patient_id' => $patient->id,
            'uploaded_by' => $currentUser->id,
            'uploaded_by_name' => trim(($currentUser->first_name ?? '') . ' ' . ($currentUser->last_name ?? ''))
                ?: ($currentUser->fullname ?? $currentUser->email ?? 'Unknown'),
            'document_type' => $request->document_type,
            'document_title' => $request->document_title,
            'document_date' => $request->document_date ? Carbon::parse($request->document_date)->format('Y-m-d') : null,
            'file_url' => $fileUrl,
            'file_name' => $request->file('file') ? $request->file('file')->getClientOriginalName() : null,
        ]);

        return $this->formatDocumentRecord($uploadedDocument);
    }

    public function deletePatientDocument($id)
    {
        [, $recordId] = $this->parseRecordIdentifier($id);

        $document = PatientDocument::find($recordId);
        if (!$document) {
            return false;
        }
        $document->delete();
        return true;
    }
    public function patientVisitOverview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $records = PatientVisit::query()
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $request->patient_id)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('visitno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('service', 'name', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['payment_status']), function ($query) use ($request) {
                $query->whereRelation('patientBilling', 'payment_status', $request['payment_status']);
            })
            ->when(!empty($request['payment_method']), function ($query) use ($request) {
                $query->whereRelation('patientBilling', 'payment_method', $request['payment_method']);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('arrival_date', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('arrival_date', 'DESC');
            })
            // admission and consultedDoctor carry the ward, bed and attending
            // doctor the visit history table shows.
            ->with('patient', 'service', 'patientBilling', 'consultation.consultedDoctor', 'admission.ward', 'admission.doctor');

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function patientVisitExport($records, $format)
    {
        $exportData = $records->map(function ($visit) {
            return [
                'Date'      => $visit->created_at->format('Y-m-d H:i'),
                'Visit No'       => $visit->visitno,
                'Service Type'           => $visit->service->name ?? 'N/A',
                'Payment Status'    => $visit->patientBilling->payment_status ?? 'N/A',
                'Payment Type'  => $visit->patientBilling->payment_method ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patients_visits.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = PDF::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patients_visits.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function initiateVisit($request)
    {
        try {

            $currentUser = Auth::user();
            $tenantId = $request->header('X-Tenant-ID');
            $service = Service::find($request->service_id);
            if (empty($service)) {
                throw new \Exception("Service not found.");
            }

            $serviceUnit = ServiceUnit::where('name', 'Registration')->where('tenant_id', $tenantId)->first();
            if (empty($service)) {
                throw new \Exception("Registration service unit not found.");
            }
            // Find patient
            $patient = Patient::find($request->patient_id);
            // Initiate Patient visit
            $patientVisit = PatientVisit::create([
                'tenant_id' => $tenantId,
                'initiated_by' => $currentUser->id,
                'visitno' => 'VIS' . GeneralHelper::generateUniqueRandomId($patient->firstname),
                'patient_id' => $patient->id,
                'service_id' => $request->service_id,
                // 'stage' => PatientVisitStageEnums::VISIT,
                'arrival_date' => now(),
                'status' => PatientVisitStatusEnums::VISIT_INITIATED->value,
                'triage_status' => GeneralEnums::PENDING->value,
                'immunization_status' => $service->name == 'IMMUNIZATION' ? GeneralEnums::PENDING->value : NULL,
                'counsel_status' => $service->name == 'HIV/AIDS' ? GeneralEnums::PENDING->value : NULL,
                'natal_status' => $service->name == 'ANTENATAL' ? GeneralEnums::PENDING->value : NULL,

            ]);

            $invoiceNumber = GeneralHelper::getModelUniqueOrderlyId([
                'modelNamespace' => BillingLog::class,
                'modelField' => 'invoice_number',
                'prefix' => 'INV-',
                'idLength' => 6,
            ]);

            //store billing info
            $patientBilling = BillingLog::create([
                'tenant_id' => $tenantId,
                'updated_by' => $currentUser->id,
                'visit_id' => $patientVisit->id,
                'patient_id' => $patient->id,
                'invoice_number' => $invoiceNumber,
                'patient_name' => $patient->firstname . ' ' . $patient->lastname,
                'billing_date' => now(),
                'service_type_id' => $request->service_id,
                'service_unit_id' => $serviceUnit->id,
                'grand_total' => $service->price
            ]);

            //update billing log details
            BillingLogDetail::create([
                'tenant_id'        => $tenantId,
                'billing_id' => $patientBilling->id,
                'service_unit_id' => $serviceUnit->id,
                'item_name' => $service->name,
                'quantity' => 1,
                'amount' => $service->price
            ]);

            $fetchAppointment = Appointment::where('patient_id', $patient->id)
                ->where('date', Carbon::now()->format('Y-m-d'))
                ->where('status', AppointmentStatusEnums::SCHEDULED->value)
                ->first();

            if ($fetchAppointment) {
                $fetchAppointment->update([
                    'visit_id' => $patientVisit->id,
                    'status' => AppointmentStatusEnums::CHECKED_IN->value,
                ]);
            }

            // update patient registaration staus
            // $patient->update([
            //     'reg_status' => GeneralEnums::FOLLOWUPPATIENT->value,
            // ]);

            return $patientVisit;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Delete a Patient by heir ID.
     *
     * @param int $id
     * @return void
     */
    public function delete(Patient $patientExists)
    {
        // Delete related patient next of kin details
        $patientExists->nextOfKin()->delete();

        // Delete related patient emergency contacts
        $patientExists->emergencyContact()->delete();

        // Finally delete the patient record
        $patientExists->delete();
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

    public function getPatientCareNotes($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $patientId = $request->patient_id;
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $query = CareNote::query()->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('visit_id', $request->visit_id)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereRelation('patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('visit', 'visitno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('writer', 'first_name', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('writer', 'last_name', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('updatedBy', 'first_name', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('updatedBy', 'last_name', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })->when(isset($request['type']), function ($query) use ($request) {
                $query->where('type', filter_var($request['type']));
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            })->with('patient', 'visit', 'writer:id,first_name,last_name,email', 'updatedBy:id,first_name,last_name,email');

        if (!empty($request['paginate'])) {
            return $query->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $query->orderBy('id', 'DESC')->get();
    }

    public function exportPatientCareNotes($careNotes, $format = null)
    {
        $exportData = $careNotes->map(function ($note) {
            return [
                'Firstname'      => $note->patient->firstname ?? 'N/A',
                'Lastname'       => $note->patient->lastname ?? 'N/A',
                'Card No'        => $note->patient->cardno ?? 'N/A',
                'Patient No'     => $note->patient->patientno ?? 'N/A',
                'Note Type'      => $note->type ?? 'N/A',
                // 'Notes'          => $note->notes ?? 'N/A',
                'Written By'     => $note->writer ? $note->writer->firstname . ' ' . $note->writer->lastname : 'N/A',
                'Date Written'   => $note->created_at ? Carbon::parse($note->created_at)->format('Y-m-d H:i:s') : 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patient_care_notes.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patient_care_notes.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function addPatientCareNote($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $currentUser = Auth::user();

        $careNote = CareNote::create([
            'tenant_id' => $tenantId,
            'patient_id' => $request->patient_id,
            'visit_id' => $request->visit_id,
            'written_by' => $currentUser ? $currentUser->id : null,
            'notes' => $request->notes,
            'type' => $request->type,
        ]);

        return $careNote->load('patient', 'visit', 'writer:id,first_name,last_name,email');
    }

    public function viewPatientCareNotes($id)
    {
        $careNote = CareNote::find($id);
        if (empty($careNote)) {
            throw new \Exception("Care note not found.");
        }
        return $careNote->load('patient', 'visit', 'writer:id,first_name,last_name,email', 'updatedBy:id,first_name,last_name,email');
    }

    public function updatePatientCareNotes($request, $id)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $currentUser = Auth::user();
        $careNote = CareNote::find($id);
        if (empty($careNote)) {
            throw new \Exception("Care note not found.");
        }

        $careNote->update([
            'updated_by' => $currentUser ? $currentUser->id : $careNote->written_by,
            'notes' => $request->notes,
            'type' => $request->type,
        ]);

        return $careNote->load('patient', 'visit', 'writer:id,first_name,last_name,email', 'updatedBy:id,first_name,last_name,email');
    }
}
