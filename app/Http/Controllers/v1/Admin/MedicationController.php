<?php

namespace App\Http\Controllers\v1\Admin;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MedicationCsvUploadRequest;
use App\Http\Requests\Admin\MedicationRequest;
use App\Responser\JsonResponser;
use App\Services\Medication\MedicationService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\User;

class MedicationController extends Controller
{
    protected $userService;
    protected $medicationService;

    public function __construct(UserService $userService, MedicationService $medicationService)
    {
        $this->userService = $userService;
        $this->medicationService = $medicationService;
    }

    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'generic_name' => "nullable|string",
                'brand_name' => "nullable|string",
                'medicine_name' => "nullable|string",
                'medicine_type' => "nullable|string",
                'medicine_status' => "nullable|string",
                'from' => "nullable|date",
                'to' => "nullable|date",
            ]);


            // $request = $request->only([
            //     'generic_name',
            //     'brand_name',
            //     'medicine_name',
            //     'medicine_type',
            //     'medicine_status',
            //     'from',
            //     'to',
            // ]);

            $data = $this->medicationService->all($validated);

            if ($data instanceof \Symfony\Component\HttpFoundation\Response) {
                return $data;
            }

            if ($data->isEmpty()) {
                return JsonResponser::send(true, 'No Medications found.', [], 204);
            }

            $formatted = $data->getCollection()->transform(function ($med) {
                return [
                    'id' => $med->id,
                    'generic_name' => $med->generic_name,
                    'brand_name' => $med->brand_name,
                    'medicine_name' => $med->medicine_name,
                    'medicine_type' => $med->medicine_type,
                    'cost_price' => $med->cost_price,
                    'selling_price' => $med->selling_price,
                    'reg_no' => $med->reg_no,
                    'manufacturer' => $med->manufacturer,
                    'medicine_status' => $med->medicine_status,
                    'pharmacy' => $med->pharmacy->name ?? null,
                    'created_at' => $med->created_at,
                ];
            });

            $paginated = $data->toArray();
            $paginated['data'] = $formatted;

            return JsonResponser::send(false, 'Medications retrieved successfully', collect($paginated));
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function store(MedicationRequest $request)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();
            // $user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            $validated = array_merge($request->validated(), [
                'created_by' => $currentUser->id,
            ]);

            $med = $this->medicationService->create($validated);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $med->id,
                'action' => 'Create',
                'action_type' => "Models\Medicine",
                'log_name' => "Medicine created successfully",
                'description' => "{$user->firstname} {$user->lastname} created a new Medicine: {$med->name}",
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Medication created successfully', $med, 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function changeStatus(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $med = $this->medicationService->find($id);
            if (!$med) {
                return JsonResponser::send(true, 'Medicine not found.', null, 204);
            }

            $validStatuses = ['available', 'out of stock', 'about to expire', 'expired'];

            $newStatus = $request->input('medicine_status');
            if (!in_array($newStatus, $validStatuses)) {
                return JsonResponser::send(true, 'Invalid status. Allowed values are: available, out of stock, about to expire, expired.', null, 422);
            }

            $med->update(['medicine_status' => $newStatus]);

            return JsonResponser::send(false, 'Medicine status updated successfully.', $med, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }


    public function show($id)
    {
        try {
            $medicine = $this->medicationService->find($id);
            if (!$medicine) {
                return JsonResponser::send(true, 'medicine not found.', null, 204);
            }

            $formatted = [
                'id' => $medicine->id,
                'generic_name' => $medicine->generic_name,
                'brand_name' => $medicine->brand_name,
                'medicine_name' => $medicine->medicine_name,
                'medicine_type' => $medicine->medicine_type,
                'cost_price' => $medicine->cost_price,
                'selling_price' => $medicine->selling_price,
                'reg_no' => $medicine->reg_no,
                'manufacturer' => $medicine->manufacturer,
                'medicine_status' => $medicine->medicine_status,
                'pharmacy' => $medicine->pharmacy->name ?? null,
                'created_at' => $medicine->created_at,
            ];

            return JsonResponser::send(false, 'medicine details retrieved successfully', $formatted, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }


    public function update(MedicationRequest $request, $id)
    {
        try {
            $data = $request->all();
            $medicine = $this->medicationService->find($id);
            if (!$medicine) {
                return JsonResponser::send(true, 'medicine not found.', null, 204);
            }

            $updatedmedicine = $this->medicationService->update($id, $data);

            return JsonResponser::send(false, 'medicine updated successfully', $updatedmedicine, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function destroy($id)
    {
        $medicine = $this->medicationService->find($id);
        if (!$medicine) {
            return JsonResponser::send(true, 'medicine not found.', null, 204);
        }
        $this->medicationService->delete($id);
        return JsonResponser::send(false, 'medicine deleted successfully', null, 200);
    }

    public function listVendors()
    {
        try {
            $vendors = $this->medicationService->getAllVendors();
            return JsonResponser::send(false, 'Vendors retrieved successfully', $vendors);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error retrieving vendors', [], 500, $e);
        }
    }

    public function uploadCsv(MedicationCsvUploadRequest $request)
    {
        try {
            $file = $request->file('file');
            $handle = fopen($file, 'r');

            if (!$handle) {
                return JsonResponser::send(true, 'Unable to open the file.', [], 422);
            }

            $header = fgetcsv($handle);
            $validColumns = ['generic_name', 'brand_name', 'medicine_name', 'medicine_type', 'cost_price', 'selling_price', 'reg_no', 'manufacturer'];

            if ($header !== $validColumns) {
                return JsonResponser::send(true, 'Invalid CSV format. Expected columns: ' . implode(', ', $validColumns), [], 422);
            }

            $medications = [];

            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, $row);

                $data['created_by'] = Auth::id();

                $medications[] = $data;
            }

            fclose($handle);

            foreach ($medications as $med) {
                $this->medicationService->create($med);
            }

            return JsonResponser::send(false, 'Medications uploaded successfully.', [], 201);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error during CSV import.', [], 500, $e);
        }
    }

    public function medicineDashboardStats()
    {
        try {
            $stats = $this->medicationService->getMedicineDashboardStats();

            return JsonResponser::send(false, 'Medicine dashboard stats fetched successfully', $stats);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Failed to fetch medicine dashboard stats', [], 500, $e);
        }
    }
}
