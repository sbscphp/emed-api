<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BillingLogRequest;
use App\Models\BillingLog;
use App\Models\ServiceDepartment;
use App\Models\ServiceUnit;
use App\Responser\JsonResponser;
use App\Services\BillingLog\BillingLogService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    protected $billingService;
    protected $userService;

    public function __construct(BillingLogService $billingService, UserService $userService)
    {
        $this->billingService = $billingService;
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        try {
            $billings = $this->billingService->all($request);
            if (
                $billings instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse ||
                $billings instanceof \Symfony\Component\HttpFoundation\StreamedResponse
            ) {
                return $billings;
            }

            if ($billings->isEmpty()) {
                return JsonResponser::send(true, 'No billing records found.', [], 404);
            }

            return JsonResponser::send(false, 'Billing logs retrieved successfully', $billings, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function store(BillingLogRequest $request)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);

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
            $billing = $this->billingService->find($id);
            if (!$billing) {
                return JsonResponser::send(true, 'Billing record not found.', null, 404);
            }

            return JsonResponser::send(false, 'Billing details retrieved successfully', $billing, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function update(BillingLogRequest $request, $id)
    {
        try {
            $billing = $this->billingService->find($id);
            if (!$billing) {
                return JsonResponser::send(true, 'Billing record not found.', null, 404);
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
            $billing = $this->billingService->find($id);
            if (!$billing) {
                return JsonResponser::send(true, 'Billing record not found.', null, 404);
            }

            $this->billingService->delete($id);

            return JsonResponser::send(false, 'Billing record deleted successfully', null, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function getAllServiceUnitsAndTypes()
    {
        try {
            $serviceUnits = ServiceUnit::select('id', 'name')->get();
            $serviceTypes = ServiceDepartment::select('id', 'name')->get();

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
            /** @var LengthAwarePaginator $billingLogs */
            $billingLogs = BillingLog::with(['patient', 'serviceType', 'serviceUnit'])
                ->where('service_unit_id', $serviceUnitId)
                ->latest()
                ->paginate(10);

            if ($billingLogs->isEmpty()) {
                return JsonResponser::send(true, 'No billing records found for this service unit.', [], 404);
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

            $totalPatients = BillingLog::where('service_unit_id', $serviceUnitId)->sum('patient_id');
            $totalAmount = BillingLog::where('service_unit_id', $serviceUnitId)->sum('grand_total');

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
            /** @var LengthAwarePaginator $billingLogs */
            $query = BillingLog::with(['patient', 'serviceType', 'serviceUnit']);

            if ($request->has('service_type_id') && $request->service_type_id !== 'all') {
                $query->where('service_type_id', $request->service_type_id);
            }

            $billingLogs = $query->paginate(10);

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
                return JsonResponser::send(true, 'No billing records found for this service type.', [], 404);
            }

            return JsonResponser::send(false, 'Billing records retrieved successfully.', $data, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $e);
        }
    }

    public function getBillingStatistics()
    {
        try {
            $query = BillingLog::query();

            $totalRevenue = (clone $query)->sum('grand_total');
            $pendingPayment = (clone $query)->where('payment_status', 'pending')->sum('grand_total');
            $completedPayment = (clone $query)->where('payment_status', 'paid')->sum('grand_total');
            $insuranceClaimed = (clone $query)->where('payment_method', 'insurance')->count();

            return JsonResponser::send(false, 'Billing stats fetched successfully.', [
                'total_revenue' => $totalRevenue,
                'pending_payment' => $pendingPayment,
                'completed_payment' => $completedPayment,
                'insurance_claimed' => $insuranceClaimed,
            ]);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching billing stats.', [], 500, $e);
        }
    }
}
