<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FulfillTreatmentRequest;
use App\Http\Requests\Admin\PharmacyRequest;
use App\Responser\JsonResponser;
use App\Services\Pharmacy\PharmacyService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Pharmacy;

class PharmacyController extends Controller
{
    protected $userService;
    protected $pharmacyService;

    public function __construct(UserService $userService, PharmacyService $pharmacyService)
    {
        $this->userService = $userService;
        $this->pharmacyService = $pharmacyService;
    }

    public function index(Request $request)
    {
        try {
            config(['database.default' => 'tenant']);
            $from = $request->from;
            $to = $request->to;
            $limit = $request->limit;
            //  $pharmacies = $this->pharmacyService->new_all($from, $to);



            // $pharmacies =  Pharmacy::with(['state:id,state_name', 'pharmacist:id,fullname,email'])
            //     ->when($from && $to, function ($q) use ($from, $to) {
            //         $q->whereBetween('created_at', [
            //             Carbon::parse($from)->startOfDay(),
            //             Carbon::parse($to)->endOfDay()
            //         ]);
            //     })
            //     ->paginate(10);

            $pharmacies = Pharmacy::with(['state:id,state_name', 'pharmacist:id,fullname,email'])
                ->when($from && $to, function ($q) use ($from, $to) {
                    $q->whereBetween('created_at', [
                        Carbon::parse($from)->startOfDay(),
                        Carbon::parse($to)->endOfDay()
                    ]);
                })->paginate($limit);


            if ($pharmacies->isEmpty()) {
                return JsonResponser::send(true, 'No pharmacies found.', [], 204);
            }

            if ($request->has('export')) {
                $exportData = $pharmacies->map(function ($pharmacy) {
                    return [
                        'Pharmacy Name'       => $pharmacy->name ?? '',
                        'Personal Information' => $pharmacy->pharmacist->fullname . ' (' . $pharmacy->pharmacist->email . ')' ?? '',
                        'Pharmacy ID'         => $pharmacy->pharmacy_id ?? '',
                        'Location'            => $pharmacy->address ?? '',
                        'Phone'             => $pharmacy->phone_number ?? '',
                        'License Number'      => $pharmacy->license_number ?? '',
                        'Prescribed Drug'     => $pharmacy->treatments->pluck('drug')->unique()->join(', '),
                        'Operating Hours'     => $pharmacy->opening_time . ' (' . $pharmacy->closing_time . ')' ?? '',
                        'Status'              => $pharmacy->active ?? '',
                    ];
                });

                if ($request->export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'pharmacies_' . now()->format('Ymd_His') . '.csv');
                }

                if ($request->export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData->toArray(), 'pharmacies_' . now()->format('Ymd_His') . '.pdf');
                }

                return JsonResponser::send(true, 'Invalid export format specified.', [], 400);
            }

            return JsonResponser::send(false, 'Pharmacies retrieved successfully', $pharmacies, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }


    public function treatmentLogs(Request $request)
    {
        try {
            config(['database.default' => 'tenant']);
            $search = $request->input('search');
            $from = $request->from;
            $to = $request->to;
            $treatments = $this->pharmacyService->treatmentLogall($search, $from,  $to);

            if ($treatments->exists()) {
                return JsonResponser::send(true, 'No treatment logs found.', [], 204);
            }

            if ($request->has('export')) {
                $exportData = $treatments->map(function ($treatment) {
                    return [
                        'Patient Name'     => $treatment->patient->firstname . ' ' . $treatment->patient->lastname,
                        'Card No'          => $treatment->patient->cardno ?? '',
                        'Patient Type'     => $treatment->patient->patient_type ?? '',
                        'Patient No'       => $treatment->patient->patientno ?? '',
                        'Pharmacy Name'    => $treatment->pharmacy->name ?? '',
                        'Prescribed Drug'  => $treatment->drug ?? '',
                        'Patient Status'   => $treatment->patient->status ?? '',
                        'Status'           => $treatment->receiptno ? 'Fulfilled' : 'Not Fulfilled',
                    ];
                });

                if ($request->export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'treatment_logs.csv');
                }

                if ($request->export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData->toArray(), 'treatment_logs.pdf');
                }

                return JsonResponser::send(true, 'Invalid export format specified.', [], 400);
            }

            return JsonResponser::send(false, 'Treatment logs retrieved successfully', $treatments, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function showPatientTreatment(Request $request, $patientId)
    {
        try {
            config(['database.default' => 'tenant']);
            $patient = $this->pharmacyService->getPatientTreatmentWithDetails($patientId);

            if (!$patient) {
                return JsonResponser::send(true, 'Patient not found.', [], 204);
            }

            return JsonResponser::send(false, 'Patient Treatment Record Fetched.', [
                'patient' => $patient,
                'treatments' => $patient->treatments
            ], 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function fulfillTreatment(FulfillTreatmentRequest $request)
    {
        return $this->pharmacyService->fulfillPrescription($request->validated());
    }

    public function store(PharmacyRequest $request)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();
            // $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            $validated = array_merge($request->validated(), [
                'pharmacy_id' => $this->pharmacyService->generatePharmacyId(),
                'created_by' => $currentUser->id,
            ]);

            $pharmacy = $this->pharmacyService->create($validated);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $pharmacy->id,
                'action' => 'Create',
                'action_type' => "Models\Pharmacy",
                'log_name' => "Pharmacy created successfully",
                'description' => "{$user->firstname} {$user->lastname} created a new pharmacy: {$pharmacy->name}",
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Pharmacy created successfully', $pharmacy, 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function toggleStatus($id)
    {
        try {
            config(['database.default' => 'tenant']);
            $pharmacy = $this->pharmacyService->find($id);
            if (!$pharmacy) {
                return JsonResponser::send(true, 'Pharmacy not found.', null, 204);
            }

            $newStatus = $pharmacy->active ? 0 : 1;
            $pharmacy->update(['active' => $newStatus]);

            return JsonResponser::send(false, 'Pharmacy status updated successfully', $pharmacy, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function show($id)
    {
        try {
            config(['database.default' => 'tenant']);
            $pharmacy = $this->pharmacyService->find($id);
            if (!$pharmacy) {
                return JsonResponser::send(true, 'Pharmacy not found.', null, 204);
            }

            return JsonResponser::send(false, 'Pharmacy details retrieved successfully', $pharmacy, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function update(PharmacyRequest $request, $id)
    {
        try {
            config(['database.default' => 'tenant']);
            $data = $request->all();
            $pharmacy = $this->pharmacyService->find($id);
            if (!$pharmacy) {
                return JsonResponser::send(true, 'Pharmacy not found.', null, 204);
            }

            $updatedPharmacy = $this->pharmacyService->update($data, $id);

            return JsonResponser::send(false, 'Pharmacy updated successfully', $updatedPharmacy, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function destroy($id)
    {
        config(['database.default' => 'tenant']);
        $pharmacy = $this->pharmacyService->find($id);
        if (!$pharmacy) {
            return JsonResponser::send(true, 'Pharmacy not found.', null, 204);
        }
        $this->pharmacyService->delete($id);
        return JsonResponser::send(false, 'Pharmacy deleted successfully', null, 200);
    }

    public function pharmacyDashboardStats()
    {
        try {
            config(['database.default' => 'tenant']);
            $stats = $this->pharmacyService->getDashboardStats();

            return JsonResponser::send(false, 'Pharmacy dashboard stats fetched successfully', $stats);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Failed to fetch pharmacy dashboard stats', [], 500, $e);
        }
    }
}
