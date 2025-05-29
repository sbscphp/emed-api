<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PharmacyRequest;
use App\Responser\JsonResponser;
use App\Services\Pharmacy\PharmacyService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            $pharmacies = $this->pharmacyService->all($request);

            if ($pharmacies->isEmpty()) {
                return JsonResponser::send(true, 'No pharmacies found.', [], 404);
            }

            if ($request->has('export')) {
                $exportData = $pharmacies->map(function ($pharmacy) {
                    return [
                        'Pharmacy Name' => $pharmacy->name ?? '',
                        'State' => $pharmacy->state->state_name ?? '',
                        'Pharmacist' => $pharmacy->pharmacist->fullname ?? '',
                        'Pharmacist Email' => $pharmacy->pharmacist->email ?? '',
                        'Patients Assigned' => $pharmacy->treatments->pluck('patient.firstname')->unique()->join(', ')
                    ];
                });

                if ($request->export === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'pharmacies.csv');
                }

                if ($request->export === 'pdf') {
                    return ExportHelper::downloadPdf($exportData->toArray(), 'pharmacies.pdf');
                }

                return JsonResponser::send(true, 'Invalid export format specified.', [], 400);
            }

            return JsonResponser::send(false, 'Pharmacies retrieved successfully', $pharmacies, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }


    public function store(PharmacyRequest $request)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();
            $user = $this->userService->find($currentUser->id);

            $validated = array_merge($request->validated(), [
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
            $pharmacy = $this->pharmacyService->find($id);
            if (!$pharmacy) {
                return JsonResponser::send(true, 'Pharmacy not found.', null, 404);
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
            $pharmacy = $this->pharmacyService->find($id);
            if (!$pharmacy) {
                return JsonResponser::send(true, 'Pharmacy not found.', null, 404);
            }

            return JsonResponser::send(false, 'Pharmacy details retrieved successfully', $pharmacy, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function update(PharmacyRequest $request, $id)
    {
        try {
            $data = $request->all();
            $pharmacy = $this->pharmacyService->find($id);
            if (!$pharmacy) {
                return JsonResponser::send(true, 'Pharmacy not found.', null, 404);
            }

            $updatedPharmacy = $this->pharmacyService->update($data, $id);

            return JsonResponser::send(false, 'Pharmacy updated successfully', $updatedPharmacy, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function destroy($id)
    {
        $pharmacy = $this->pharmacyService->find($id);
        if (!$pharmacy) {
            return JsonResponser::send(true, 'Pharmacy not found.', null, 404);
        }
        $this->pharmacyService->delete($id);
        return JsonResponser::send(false, 'Pharmacy deleted successfully', null, 200);
    }

    public function pharmacyDashboardStats()
    {
        try {
            $stats = $this->pharmacyService->getDashboardStats();

            return JsonResponser::send(false, 'Pharmacy dashboard stats fetched successfully', $stats);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Failed to fetch pharmacy dashboard stats', [], 500, $e);
        }
    }
}
