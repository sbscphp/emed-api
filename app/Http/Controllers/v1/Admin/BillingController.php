<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BillingLogRequest;
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
use App\Models\ServiceDepartment;

class BillingController extends Controller
{
    protected $billingService;
    protected $userService;
    protected $serviceFetch;

    public function __construct(BillingLogService $billingService, UserService $userService,  ServiceDepartmentService $serviceFetch)
    {
        $this->billingService = $billingService;
        $this->userService = $userService;
        $this->serviceFetch = $serviceFetch;
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

    public function billingsummary()
    {
        // dd('here');
        try {
            DB::connection('tenant')->beginTransaction();
            // $billingSummaries = BillingLog::with('serviceUnit')
            //     ->get();
            $billingSummaries = BillingLog::select('service_unit_id', DB::raw('SUM(grand_total) as total_billing'))
                ->groupBy('service_unit_id')
                ->with('serviceUnit')
                ->get();

            // dd(json_encode($billingSummaries));
            return JsonResponser::send(false, 'Billing summary fetched successfully.', $billingSummaries, 200);
            // return JsonResponser::send(false, 'Billing records retrieved successfully.', $data, 200);
            // return JsonResponser::send(false, 'Record(s) found successfully.', $logs);

        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Error fetching billing summary stats.', [], 500, $th);
        }
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
}
