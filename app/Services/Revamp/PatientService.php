<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\ListModuleEnums;
use App\Enums\PatientVisitStageEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Models\Patient;
use App\Repositories\Patient\PatientInterface;
use App\Helpers\ExportHelper;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Models\BillingLog;
use App\Models\BillingLogDetail;
use App\Models\Consultation;
use App\Models\CounsellingDetail;
use App\Models\EmergencyContact;
use App\Models\Immunization;
use App\Models\Laboratory;
use App\Models\LaboratoryResult;
use App\Models\PatientDocument;
use App\Models\RadiologyResult;
use App\Models\NextOfKin;
use App\Models\Radiology;
use App\Models\PatientVisit;
use App\Models\Service;
use App\Models\ServiceUnit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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
            return [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'document_title' => $document->document_title,
                'document_date' => optional($document->document_date)->toDateString(),
                'uploaded_by_id' => $document->uploaded_by,
                'uploaded_by' => $document->uploaded_by_name,
                'file_url' => $document->file_url,
                'file_name' => $document->file_name,
                'created_at' => $document->created_at->toDateTimeString(),
                'updated_at' => $document->updated_at->toDateTimeString(),
            ];
        };

        if ($request->boolean('paginate', false)) {
            $documents = $query->paginate($request->limit ?? 15);
            $documents->getCollection()->transform($mapDocument);
            return $documents;
        }

        return $query->get()->map($mapDocument)->all();
    }

    public function fetchPatientDocumentsBundle($patientExists, $request): array
    {
        $patient = $patientExists instanceof Patient ? $patientExists : Patient::find($patientExists->id);
        if (!$patient) {
            return [
                'documents' => [],
                'lab_tests' => [],
                'radiology_tests' => [],
                'records' => [],
            ];
        }

        $request = $request ?? request();

        $documentsRequest = $request->duplicate();
        $documentsRequest->merge(['paginate' => false]);
        $documents = $this->fetchDocuments($patient, $documentsRequest);

        $customDate = [];
        if ($request->filled('period') && $request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }
        $dateFilter = $request->filled('period') ? GeneralHelper::dateFilter($request->period, $customDate) : null;

        $labTestsQuery = Laboratory::query()
            ->with(['results'])
            ->where('patient_id', $patient->id)
            ->when($request->filled('search_param'), function ($query) use ($request) {
                $search = '%' . $request->search_param . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('test_name', 'LIKE', $search)
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
            ->orderBy('created_at', 'DESC');

        $labTests = $labTestsQuery->get()->map(function ($test) {
            return [
                'record_type' => 'lab_test',
                'id' => $test->id,
                'visit_id' => $test->visit_id,
                'consultation_id' => $test->consultation_id,
                'test_id' => $test->test_id,
                'test_name' => $test->test_name,
                'department' => $test->department,
                'specimen_type' => $test->specimen_type,
                'notes' => $test->notes,
                'status' => $test->status,
                'results_count' => $test->results?->count() ?? 0,
                'created_at' => optional($test->created_at)->toDateTimeString(),
                'updated_at' => optional($test->updated_at)->toDateTimeString(),
            ];
        })->all();

        $radiologyTestsQuery = Radiology::query()
            ->with(['results'])
            ->where('patient_id', $patient->id)
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
            ->orderBy('created_at', 'DESC');

        $radiologyTests = $radiologyTestsQuery->get()->map(function ($test) {
            return [
                'record_type' => 'radiology_test',
                'id' => $test->id,
                'visit_id' => $test->visit_id,
                'consultation_id' => $test->consultation_id,
                'test_id' => $test->test_id,
                'test_name' => $test->test_name,
                'department' => $test->department,
                'status' => $test->status,
                'results_count' => $test->results?->count() ?? 0,
                'created_at' => optional($test->created_at)->toDateTimeString(),
                'updated_at' => optional($test->updated_at)->toDateTimeString(),
            ];
        })->all();

        $documentRecords = collect($documents)->map(function ($document) {
            return array_merge(
                [
                    'record_type' => 'patient_document',
                ],
                is_array($document) ? $document : (array) $document
            );
        })->all();

        $records = array_merge($documentRecords, $labTests, $radiologyTests);
        usort($records, function ($a, $b) {
            return strtotime($b['created_at'] ?? '1970-01-01 00:00:00') <=> strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
        });

        if ($request->boolean('paginate', false)) {
            $page = (int) ($request->query('page', 1));
            $perPage = (int) ($request->limit ?? $request->per_page ?? 15);
            $perPage = $perPage > 0 ? $perPage : 15;

            $total = count($records);
            $items = array_slice($records, max(0, ($page - 1) * $perPage), $perPage);

            $records = new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        }

        return [
            'documents' => $documents,
            'lab_tests' => $labTests,
            'radiology_tests' => $radiologyTests,
            'records' => $records,
        ];
    }

    public function showPatientDocument($id)
    {
        $document = PatientDocument::find($id);
        if (!$document) {
            throw new \Exception('Document not found.');
        }

        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'document_title' => $document->document_title,
            'document_date' => optional($document->document_date)->toDateString(),
            'uploaded_by_id' => $document->uploaded_by,
            'uploaded_by' => $document->uploaded_by_name,
            'file_url' => $document->file_url,
            'file_name' => $document->file_name,
            'created_at' => $document->created_at->toDateTimeString(),
            'updated_at' => $document->updated_at->toDateTimeString(),
        ];
    }

    public function showPatientRecord($id, string $recordType): array
    {
        $recordType = strtolower(trim($recordType));

        if (in_array($recordType, ['patient_document', 'document', 'patient-doc'], true)) {
            return array_merge(
                ['record_type' => 'patient_document'],
                $this->showPatientDocument($id)
            );
        }

        if (in_array($recordType, ['lab_test', 'lab', 'laboratory', 'lab_result', 'laboratory_result'], true)) {
            $test = Laboratory::with(['results.parameter'])->find($id);
            if (!$test) {
                throw new \Exception('Lab test not found.');
            }

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

            return [
                'record_type' => 'lab_test',
                'id' => $test->id,
                'patient_id' => $test->patient_id,
                'visit_id' => $test->visit_id,
                'consultation_id' => $test->consultation_id,
                'test_id' => $test->test_id,
                'test_name' => $test->test_name,
                'department' => $test->department,
                'specimen_type' => $test->specimen_type,
                'notes' => $test->notes,
                'status' => $test->status,
                'results' => $results,
                'created_at' => optional($test->created_at)->toDateTimeString(),
                'updated_at' => optional($test->updated_at)->toDateTimeString(),
            ];
        }

        if (in_array($recordType, ['radiology_test', 'radiology', 'radiology_result', 'radio_result'], true)) {
            $test = Radiology::with(['results'])->find($id);
            if (!$test) {
                throw new \Exception('Radiology test not found.');
            }

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

            return [
                'record_type' => 'radiology_test',
                'id' => $test->id,
                'patient_id' => $test->patient_id,
                'visit_id' => $test->visit_id,
                'consultation_id' => $test->consultation_id,
                'test_id' => $test->test_id,
                'test_name' => $test->test_name,
                'department' => $test->department,
                'status' => $test->status,
                'results' => $results,
                'created_at' => optional($test->created_at)->toDateTimeString(),
                'updated_at' => optional($test->updated_at)->toDateTimeString(),
            ];
        }

        throw new \Exception('Unsupported record type.');
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
            'uploaded_by_name' => $currentUser->first_name ?? $currentUser->last_name ?? $currentUser->email ?? 'Unknown',
            'document_type' => $request->document_type,
            'document_title' => $request->document_title,
            'document_date' => $request->document_date ? Carbon::parse($request->document_date)->format('Y-m-d') : null,
            'file_url' => $fileUrl,
            'file_name' => $request->file('file') ? $request->file('file')->getClientOriginalName() : null,
        ]);

        return [
            'id' => $uploadedDocument->id,
            'document_type' => $uploadedDocument->document_type,
            'document_title' => $uploadedDocument->document_title,
            'document_date' => optional($uploadedDocument->document_date)->toDateString(),
            'uploaded_by_id' => $uploadedDocument->uploaded_by,
            'uploaded_by' => $uploadedDocument->uploaded_by_name,
            'file_url' => $uploadedDocument->file_url,
            'created_at' => $uploadedDocument->created_at->toDateTimeString(),
            'updated_at' => $uploadedDocument->updated_at->toDateTimeString(),
        ];
    }
    public function deletePatientDocument($id)
    {
        $document = PatientDocument::find($id);
        if (!$document) {
            throw new \Exception('Document not found.');
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
            ->with('patient', 'service', 'patientBilling', 'consultation');

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
}
