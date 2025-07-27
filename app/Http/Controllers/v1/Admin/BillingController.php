<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BillingLogRequest;
use App\Http\Requests\BillingDaftRequest;
use App\Responser\JsonResponser;
use App\Services\BillingLog\BillingLogService;
use App\Services\ServiceDepartment\ServiceDepartmentService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Http\Resources\Billingresource;
use App\Models\BillingLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use App\Http\Requests\CreateServiceRequest;
use App\Http\Resources\BillingLogResource;
use App\Http\Resources\BillingLogSubmmaryResource;
use App\Http\Resources\ConsultationBillingmgt;
use App\Http\Resources\ConsultationResource;
use App\Http\Resources\LaboratoryBillingmgt;
use App\Http\Resources\MedicationResource;
use App\Http\Resources\PharmacyResourceList;
use App\Http\Resources\RadiologyBillingmgt;
use App\Http\Resources\RadiologyResourceBilling;
use App\Http\Resources\RegistrationBillingmgt;
use App\Http\Resources\RegistrationResource;
use App\Models\Consultation;
use App\Models\Laboratory;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Pharmacy;
use App\Models\Radiology;
use App\Models\ServiceDepartment;
use App\Models\ServiceUnit;
use App\Models\Treatment;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;

class BillingController extends Controller
{
    protected $billingService;
    protected $userService;
    protected $serviceFetch;
    protected $repo;
    public function __construct(BillingLogService $billingService, UserService $userService,  ServiceDepartmentService $serviceFetch, BillingLogRepositoryInterface $repo)
    {
        $this->billingService = $billingService;
        $this->userService = $userService;
        $this->serviceFetch = $serviceFetch;
        $this->repo = $repo;
    }

    public function index(Request $request)
    {
        DB::connection('tenant')->beginTransaction();
        // try {
        config(['database.default' => 'tenant']);

        $validated =  $request->validate([
            "search" => "nullable|string",
            "payment_status" => "nullable|string",
            "patient_service_type" => "nullable|string",
            "patient_service_unit" => "nullable|string",
            "export" => "nullable|string",
            'from' => "nullable|string",
            'to' => "nullable|string",
            'patient_type' => "nullable|string",
            'item' => "nullable|string"
            // 'payment_status' => "nullable|string"
        ]);

        if ($request['export'] === 'csv') {

            $billinglog = BillingLog::with(['serviceType', 'serviceUnit', 'patient.service'])->get();
            $exportData = Billingresource::collection($billinglog)->resolve();
            return ExportHelper::streamCsv($exportData, null, 'billing-records.csv');
        }

        if ($request['export'] === 'pdf') {
            $billinglog = BillingLog::with(['serviceType', 'serviceUnit', 'patient.service'])->get();
            $exportData = Billingresource::collection($billinglog)->resolve();
            $html = view('exports.patients', ['patients' => $exportData])->render();
            $pdf = Pdf::loadHTML($html)->setPaper('A1', 'landscape');
            return Response::make($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="patient_report_log.pdf"',
            ]);
        }
        $billings = $this->billingService->all($validated);
        if (
            $billings instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse ||
            $billings instanceof \Symfony\Component\HttpFoundation\StreamedResponse
        ) {
            return $billings;
        }

        if ($billings->isEmpty()) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'No billing records found.', [], 200);
        }

        return JsonResponser::send(false, 'Billing logs retrieved successfully', $billings, 200);
        // } catch (\Exception $e) {
        //     return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        // }
    }

    // serviceFetch

    public function createservice(CreateServiceRequest $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $validated =  $request->validated();
            $service = $this->serviceFetch->create_service($validated);
            //   $record = ServiceDepartment::findOrFail($id);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Service created successfully', $service, 200);
            if (!$service) {
                DB::connection('tenant')->rollBack();
                return JsonResponser::send(true, 'Service not found', [], 404);
            }
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function editservice(Request $request)
    {
        // dd($request->all());
        try {

            DB::connection('tenant')->beginTransaction();

            $validated =  $request->validate([
                'id' => 'nullable|numeric',
                'name' => 'required|string'
            ]);
            $service = $this->serviceFetch->editservice($validated);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Service created successfully', $service, 200);
            if (!$service) {
                DB::connection('tenant')->rollBack();
                return JsonResponser::send(true, 'Service not found', [], 404);
            }
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function store(BillingLogRequest $request)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            $validated = array_merge($request->validated(), [
                'created_by' => $currentUser->id,
            ]);

            $billing = $this->billingService->create($validated);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $billing->id,
                'action' => 'Create',
                'action_type' => "Models\BillingLog",
                'log_name' => "Billing record created successfully",
                'description' => "{$user->firstname} {$user->lastname} created a billing log for patient: {$billing->patient_name}",
                'module_accessed' => ListModuleEnums::BILLING
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Billing record created successfully', $billing, 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    private function generateInvoiceNumber()
    {
        $lastBilling = $this->repo->getLatest();

        if ($lastBilling && $lastBilling->invoice_number) {
            $lastNumber = (int) str_replace('INV-', '', $lastBilling->invoice_number);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return 'INV-' . $nextNumber;
    }
    public function save_as_daft(BillingDaftRequest $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $validated = $request->validated();
            $validated['payment_status'] = 'pending';
            $validated['invoice_number'] = $this->generateInvoiceNumber();
            $billing = BillingLog::create($validated);
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Billing record created successfully', $billing, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function show($id)
    {
        try {
            config(['database.default' => 'tenant']);
            $billing = $this->billingService->find($id);
            if (!$billing) {
                return JsonResponser::send(true, 'Billing record not found.', null, 200);
            }

            return JsonResponser::send(false, 'Billing details retrieved successfully', $billing, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function update(BillingLogRequest $request, $id)
    {
        try {
            config(['database.default' => 'tenant']);
            $billing = $this->billingService->find($id);
            if (!$billing) {
                return JsonResponser::send(true, 'Billing record not found.', null, 200);
            }

            $updated = $this->billingService->update($id, $request->all());

            return JsonResponser::send(false, 'Billing record updated successfully', $updated, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function destroy($id)
    {
        try {
            config(['database.default' => 'tenant']);
            $billing = $this->billingService->find($id);
            if (!$billing) {
                return JsonResponser::send(true, 'Billing record not found.', null, 200);
            }

            $this->billingService->delete($id);

            return JsonResponser::send(false, 'Billing record deleted successfully', null, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function getAllServiceUnitsAndTypes(Request $request)
    {
        try {
            config(['database.default' => 'tenant']);

            $serviceUnits = $this->serviceFetch->getUnits(['id', 'name'], $request->get('service_units_name'), $request->get('from'), $request->get('to'));
            $serviceTypes = $this->serviceFetch->getTypes(['id', 'name'], $request->get('service_types_name'), $request->get('from'), $request->get('to'));

            $data = [
                'service_units' => $serviceUnits,
                'service_types' => $serviceTypes,
            ];

            return JsonResponser::send(false, 'Service units and types retrieved successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $e);
        }
    }

    public function getBillingByServiceUnit($serviceUnitId)
    {
        try {
            config(['database.default' => 'tenant']);
            /** @var LengthAwarePaginator $billingLogs */

            $billingLogs = $this->billingService->getByServiceUnit($serviceUnitId);

            if ($billingLogs->isEmpty()) {
                return JsonResponser::send(true, 'No billing records found for this service unit.', [], 200);
            }


            $transformed = $billingLogs->getCollection()->map(function ($log) {
                return [
                    'id' =>  $log->id,
                    'patient_name' => ($log->patient->firstname ?? '') . ' ' . ($log->patient->lastname ?? ''),
                    'patient_no' => $log->patient->patientno ?? null,
                    'gender' => $log->patient->gender ?? null,
                    'age' => $log->patient ? \Carbon\Carbon::parse($log->patient->dob)->age : null,
                    'service_type' => $log->serviceType->name ?? null,
                    'service_unit' => $log->serviceUnit->name ?? null,
                    'payment_type' => $log->payment_status,
                    'amount' => $log->grand_total,
                    'date_billed' => $log->billing_date,
                ];
            });

            $billingLogs->setCollection($transformed);
            $totalPatients = $this->billingService->sumByServiceUnit($serviceUnitId, 'patient_id');
            $totalAmount = $this->billingService->sumByServiceUnit($serviceUnitId, 'grand_total');


            $response = [
                'logs' => $billingLogs,
                'stats' => [
                    'total_patients' => $totalPatients,
                    'total_amount_realized' => $totalAmount,
                ],
            ];

            return JsonResponser::send(false, 'Billing records retrieved successfully.', $response, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function getBillingByServiceType(Request $request)
    {
        try {
            config(['database.default' => 'tenant']);
            $billingLogs = $this->billingService->getByServiceType(intval($request->input('service_type_id')));

            $logs = collect($billingLogs->items())->map(function ($log) {
                return [
                    'id' =>  $log->id,
                    'patient_name' => ($log->patient->firstname ?? '') . ' ' . ($log->patient->lastname ?? ''),
                    'patient_no' => $log->patient->patientno ?? null,
                    'gender' => $log->patient->gender ?? null,
                    'age' => $log->patient ? \Carbon\Carbon::parse($log->patient->dob)->age : null,
                    'service_type' => $log->serviceType->name ?? null,
                    'service_unit' => $log->serviceUnit->name ?? null,
                    'payment_type' => $log->payment_status,
                    'amount' => $log->grand_total,
                    'date_billed' => $log->billing_date,
                ];
            });

            // Stats
            $totalPatientIdSum = $billingLogs->getCollection()->sum('patient_id');
            $totalAmount = $billingLogs->getCollection()->sum('grand_total');

            $data = [
                'records' => $logs,
                'pagination' => [
                    'total' => $billingLogs->total(),
                    'per_page' => $billingLogs->perPage(),
                    'current_page' => $billingLogs->currentPage(),
                    'last_page' => $billingLogs->lastPage(),
                ],
                'stats' => [
                    'total_patient_id_sum' => $totalPatientIdSum,
                    'total_amount_realized' => $totalAmount,
                ]
            ];

            if ($logs->isEmpty()) {
                return JsonResponser::send(true, 'No billing records found for this service type.', [], 200);
            }

            return JsonResponser::send(false, 'Billing records retrieved successfully.', $data, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $e);
        }
    }

    public function billingsummary(Request $request)
    {
        // dd('here');
        try {
            DB::connection('tenant')->beginTransaction();
            // $billingSummaries = BillingLog::with('serviceUnit')
            //     ->get();
            $validated =   $request->validate([
                'export' => 'nullable|string',
                'service_unit' => "nullable|string",
            ]);
            $billingSummaries = BillingLog::select(
                'service_unit_id',
                'payment_status',
                DB::raw('SUM(grand_total) as total_billing'),
                DB::raw('COALESCE(SUM(deposit_amount), 0) as amount_paid'),
                DB::raw('COALESCE(SUM(deposit_amount), 0) - SUM(grand_total) as outstanding_amount'),
                DB::raw('COUNT(patient_id) as total_invoice')
            )
                ->groupBy('service_unit_id')
                ->with('serviceUnit')
                ->when(!empty($validated['service_unit']), function ($query) use ($validated) {
                    $query->whereHas('serviceUnit', function ($q) use ($validated) {
                        $q->where('name', $validated['service_unit']);
                    });
                })
                ->get();
            if (count($billingSummaries) == 0) {
                return JsonResponser::send(true, 'No Data.', [], 500);
            }
            $exportData =  BillingLogSubmmaryResource::collection($billingSummaries)->resolve();
            if (!empty($validated['export'])) {

                $export =  $validated['export'];

                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'audit-logs.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'audit-logs.pdf');
                }
            }

            return JsonResponser::send(false, 'Billing summary fetched successfully.', $exportData, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching billing summary stats.', [], 500, $th);
        }
    }

    public function  registration_list(Request $request)
    {

        try {
            DB::connection('tenant')->beginTransaction();
            $validated =   $request->validate([
                'export' => "nullable|string",
                'search' => 'nullable|string',
                'start_date' => "nullable|string",
                'end_date' => "nullable|string",
            ]);
            $data = $this->billingService->registration_list($validated);
            if (!empty($validated['export'])) {
                $export =  $validated['export'];
                // $patient =  Patient::with('visits_recent.billingLogsForPatient')->get();
                $patient =  BillingLog::with(['serviceUnit', 'patient', 'visits_recent.consultation.pharmacist', 'visits_recent.billingLogsForPatient'])->where('service_unit_id', 3)->get();
                if (count($patient) == 0) {
                    return JsonResponser::send(true, 'No Data.', [], 500);
                }
                $exportData = RegistrationResource::collection($patient)->resolve();

                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'audit-logs.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'audit-logs.pdf');
                }
            }
            return JsonResponser::send(false, 'Billing stats fetched successfully.', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching billing summary stats.', [], 500, $th);
        }
    }


    public function pharmacy_list(Request $request)
    {
        DB::connection('tenant')->beginTransaction();
        $validated =   $request->validate([
            'export' => "nullable|string",
            'search' => 'nullable|string',
            'start_date' => "nullable|string",
            'end_date' => "nullable|string",
        ]);

        $data = $this->billingService->pharmacy_list($validated);
        $pharm =  BillingLog::with(['serviceUnit', 'patient', 'visits_recent.consultation.pharmacist'])->where('service_unit_id', 3)->get();
        if (count($pharm) == 0) {
            return JsonResponser::send(true, 'No Data.', [], 500);
        }
        if (!empty($validated['export'])) {
            $export =  $validated['export'];
            $exportData = PharmacyResourceList::collection($pharm)->resolve();

            if ($export === 'csv') {
                return ExportHelper::streamCsv($exportData, null, 'pharmacy.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($exportData, 'pharmacy.pdf');
            }
        }
        return JsonResponser::send(false, 'Billing stats fetched successfully.', $data);
    }


    public function consultation_list(Request $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $validated =   $request->validate([
                'export' => "nullable|string",
                'search' => 'nullable|string',
                'start_date' => "nullable|string",
                'end_date' => "nullable|string",
            ]);
            $consultation =  BillingLog::with(['serviceUnit', 'patient'])->where('service_unit_id', 3)->get();
            if (count($consultation) == 0) {
                return JsonResponser::send(true, 'No Data.', [], 500);
            }
            $data = $this->billingService->consultation_list($validated);
            if (!empty($validated['export'])) {
                $export =  $validated['export'];
                // $exportData = PharmacyResourceList::collection($pharm)->resolve();
                $exportData = ConsultationResource::collection($consultation)->resolve();
                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'Consultation.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'Consultation.pdf');
                }
            }
            return JsonResponser::send(false, ' fetched successfully.',  $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching.', [], 500, $th);
        }
    }


    public function laboratory_list(Request $request)
    {

        // try {
        DB::connection('tenant')->beginTransaction();
        $validated =   $request->validate([
            'export' => "nullable|string",
            'search' => 'nullable|string',
            'start_date' => "nullable|string",
            'end_date' => "nullable|string",
        ]);
        // $laboratory = Laboratory::with('patient.visits_recent.billingLogsForPatient')->get();
        // if (count($laboratory) == 0) {
        //     return JsonResponser::send(true, 'No Data.', [], 500);
        // }
        // $data = $this->billingService->laboratory_list($validated);
        // if (!empty($validated['export'])) {
        //     $export =  $validated['export'];
        //     // $exportData = PharmacyResourceList::collection($pharm)->resolve();
        //     $exportData = ConsultationResource::collection($laboratory)->resolve();
        //     if ($export === 'csv') {
        //         return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
        //     }

        //     if ($export === 'pdf') {
        //         return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
        //     }
        // }
        // return JsonResponser::send(false, ' fetched successfully.',  $data);



        $data = $this->billingService->laboratory_list($validated);
        if (!empty($validated['export'])) {
            $export =  $validated['export'];
            // $exportData = PharmacyResourceList::collection($pharm)->resolve();
            $Laboratory = BillingLog::with(['serviceUnit', 'patient.laboratory'])->where('service_unit_id', 4)->get();
            $exportData = LaboratoryBillingmgt::collection($Laboratory)->resolve();
            if ($export === 'csv') {
                return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
            }
        }

        return JsonResponser::send(false, ' fetched successfully.',  $data);
        // } catch (\Throwable $th) {
        //     return JsonResponser::send(true, 'Error fetching.', [], 500, $th);
        // }
    }

    public function radiology_list(Request $request)
    {

        $validated =   $request->validate([
            'export' => "nullable|string",
            'search' => 'nullable|string',
            'start_date' => "nullable|string",
            'end_date' => "nullable|string",
            'payment_method' => "nullable|string",
            'payment_status' => "nullable|string",
        ]);

        // $radiology =  Radiology::with('patient.visits_recent.billingLogsForPatient')->get();
        // if (count($radiology) == 0) {
        //     return JsonResponser::send(false, 'No Data.', [], 200);
        // }
        // $data = $this->billingService->radiology_list($validated);
        // if (!empty($validated['export'])) {
        //     $export =  $validated['export'];
        //     // $exportData = PharmacyResourceList::collection($pharm)->resolve();
        //     $exportData = ConsultationResource::collection($radiology)->resolve();
        //     if ($export === 'csv') {
        //         return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
        //     }

        //     if ($export === 'pdf') {
        //         return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
        //     }
        // }


        $data = $this->billingService->radiology_list($validated);
        if (!empty($validated['export'])) {
            $export =  $validated['export'];
            // $exportData = PharmacyResourceList::collection($pharm)->resolve();
            $radiology = BillingLog::with(['serviceUnit', 'patient'])->where('service_unit_id', 5)->get();
            $exportData = RadiologyResourceBilling::collection($radiology)->resolve();
            if ($export === 'csv') {
                return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
            }
        }

        return JsonResponser::send(false, ' fetched successfully.',  $data);
    }

    public function payment_daft(Request $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $validated =   $request->validate([
                'export' => "nullable|string",
                'search' => 'nullable|string',
                'start_date' => "nullable|string",
                'end_date' => "nullable|string",
                'service_type' => "nullable|string",
                'paid_type' => 'nullable|string|in:paid,part_paid,pending'
            ]);

            $billingLog =   BillingLog::with(['serviceType', 'patient'])->get();
            if (count($billingLog) == 0) {
                return JsonResponser::send(true, 'No Data.', [], 500);
            }
            $data = $this->billingService->billingLog($validated);
            if (!empty($validated['export'])) {
                $export =  $validated['export'];
                // $exportData = PharmacyResourceList::collection($pharm)->resolve();

                $exportData = BillingLogResource::collection($billingLog)->resolve();
                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
                }
            }

            return JsonResponser::send(false, ' fetched successfully.',  $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching.', [], 500, $th);
        }
    }


    public function billingmgt()
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $data  = $this->billingService->billingmgt();
            return JsonResponser::send(false, ' fetched successfully.',  $data);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Error fetching.', [], 500, $th);
        }
    }


    public function billingmgt_pharmacy(Request $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $validated =   $request->validate([
                'export' => "nullable|string",
                'search' => 'nullable|string',
                'start_date' => "nullable|string",
                'end_date' => "nullable|string",
            ]);

            $med =  Pharmacy::with('medication')->get();
            if (count($med) == 0) {
                return JsonResponser::send(true, 'No Data.', [], 500);
            }
            $data = $this->billingService->billingmgt_pharmacy($validated);
            if (!empty($validated['export'])) {
                $export =  $validated['export'];
                // $exportData = PharmacyResourceList::collection($pharm)->resolve(); MedicationResource

                $exportData = MedicationResource::collection($med)->resolve();
                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
                }
            }
            return JsonResponser::send(false, ' fetched successfully.',  $data);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Error fetching.', [], 500, $th);
        }
    }


    public function regstration_billingmgt(Request $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $validated =   $request->validate([
                'export' => "nullable|string",
                'search' => 'nullable|string',
                'start_date' => "nullable|string",
                'end_date' => "nullable|string",
            ]);

            $data = $this->billingService->regstration_billingmgt($validated);

            if (!empty($validated['export'])) {
                $export =  $validated['export'];
                // $exportData = PharmacyResourceList::collection($pharm)->resolve(); MedicationResource
                $service = ServiceDepartment::with('patients.visits_recent.billingLogsForPatient')->get();
                if (count($service) == 0) {
                    return JsonResponser::send(true, 'No Data.', [], 500);
                }
                $exportData = RegistrationBillingmgt::collection($service)->resolve();
                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'Registration.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'Registration.pdf');
                }
            }
            return JsonResponser::send(false, ' fetched successfully.',  $data);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Error fetching.', [], 500, $th);
        }
    }

    public function laboratory_billingmgt(Request $request)
    {

        try {
            DB::connection('tenant')->beginTransaction();
            $validated =   $request->validate([
                'export' => "nullable|string",
                'search' => 'nullable|string',
                'start_date' => "nullable|string",
                'end_date' => "nullable|string",
            ]);
            $data = $this->billingService->laboratory_billingmgt($validated);

            if (!empty($validated['export'])) {
                $export =  $validated['export'];
                // $exportData = PharmacyResourceList::collection($pharm)->resolve(); MedicationResource
                $laboratory = Laboratory::with('patient.visits_recent.billingLogsForPatient')->get();
                if (count($laboratory) == 0) {
                    return JsonResponser::send(true, 'No Data.', [], 500);
                }
                $exportData = LaboratoryBillingmgt::collection($laboratory)->resolve();
                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
                }
            }

            return JsonResponser::send(false, ' fetched successfully.',  $data);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Error fetching.', [], 500, $th);
        }
    }


    public  function radiology_billingmgt(Request $request)
    {

        try {
            DB::connection('tenant')->beginTransaction();
            $validated =   $request->validate([
                'export' => "nullable|string",
                'search' => 'nullable|string',
                'start_date' => "nullable|string",
                'end_date' => "nullable|string",
            ]);

            $data = $this->billingService->radiology_billingmgt($validated);
            if (!empty($validated['export'])) {
                $export =  $validated['export'];
                // $exportData = PharmacyResourceList::collection($pharm)->resolve(); MedicationResource
                $radiology = Radiology::with('patient.visits_recent.billingLogsForPatient')->get();
                if (count($radiology) == 0) {
                    return JsonResponser::send(true, 'No Data.', [], 500);
                }
                $exportData = RadiologyBillingmgt::collection($radiology)->resolve();
                if ($export === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
                }

                if ($export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
                }
            }

            return JsonResponser::send(false, ' fetched successfully.',  $data);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Error fetching.', [], 500, $th);
        }
    }


    public function consultation_billingmgt(Request $request)
    {
        $validated =   $request->validate([
            'export' => "nullable|string",
            'search' => 'nullable|string',
            'start_date' => "nullable|string",
            'end_date' => "nullable|string",
        ]);
        // consultation_billingmgt

        $data = $this->billingService->consultation_billingmgt($validated);
        if (!empty($validated['export'])) {
            $export =  $validated['export'];
            // $exportData = PharmacyResourceList::collection($pharm)->resolve(); MedicationResource
            $consultation =  Consultation::with(['patient.billingLogsForPatient', 'patient.service'])->get();
            if (count($consultation) == 0) {
                return JsonResponser::send(true, 'No Data.', [], 500);
            }
            $exportData = ConsultationBillingmgt::collection($consultation)->resolve();
            if ($export === 'csv') {
                return ExportHelper::streamCsv($exportData, null, 'Laboratory.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($exportData, 'Laboratory.pdf');
            }
        }
        return JsonResponser::send(false, ' fetched successfully.',  $data);
    }


    public function getBillingStatistics()
    {
        try {
            config(['database.default' => 'tenant']);
            $stats = $this->billingService->getStatistics();

            return JsonResponser::send(false, 'Billing stats fetched successfully.', $stats);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching billing stats.', [], 500, $e);
        }
    }

    public function payment_billing_daft(Request $request)
    {
        try {
            $validated =  $request->validate([
                'visitno' => "nullable|string"
            ]);

            $arr = [];

            $data = PatientVisit::where('visitno', $validated['visitno'])->first();

            if ($data) {
                $consultation = $data ? Consultation::where('visitno', $data->visitno)->first() : null;
                $radiology = Radiology::where('visitno', $data->visitno)->first();
                $treatment = $consultation ? Treatment::where('consultation_id', $consultation->id)->first() : null;
                $billingLogsForPatient = BillingLog::where('visit_id', $data->id)->first();
                $patient = $data ? Patient::find($data->patient_id) : null;
                $service =  $billingLogsForPatient ? ServiceDepartment::find($billingLogsForPatient->service_type_id) : null;
                $serviceunit  = $billingLogsForPatient ? ServiceUnit::find($billingLogsForPatient->service_unit_id) : null;
                $arr = [
                    "patient" => $patient,
                    'Patientvisit' => $data,
                    'consultation' => $consultation,
                    'radiology' => $radiology,
                    'treatment' => $treatment,
                    'billing' => $billingLogsForPatient,
                    'service' => $service,
                    'serviceunit' => $serviceunit
                ];
            }

            return JsonResponser::send(false, 'Billing stats fetched successfully.', $arr);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching .', [], 500, $e);
        }
    }
}
