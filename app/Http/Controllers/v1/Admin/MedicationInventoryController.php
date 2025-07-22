<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMedicationInventoryRequest;
use App\Responser\JsonResponser;
use App\Services\MedicationInventoryService\MedicationInventoryService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class MedicationInventoryController extends Controller
{
    protected $userService;
    protected $inventoryService;

    public function __construct(UserService $userService, MedicationInventoryService $inventoryService)
    {
        $this->userService = $userService;
        $this->inventoryService = $inventoryService;
    }

    public function store(StoreMedicationInventoryRequest $request)
    {
        try {
            config(['database.default' => 'tenant']);
            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            $validated = array_merge($request->validated(), [
                'created_by' => $currentUser->id,
            ]);

            $med = $this->inventoryService->create($validated);

            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $med->id,
                'action' => 'Create',
                'action_type' => "Models\MedicineInventory",
                'log_name' => "Medicine Inventory created successfully",
                'description' => "{$user->firstname} {$user->lastname} created a new Medicine: {$med->name}",
                'module_accessed' => ListModuleEnums::PHARMACY
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Shipment created successfully', $med);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function index(Request $request)
    {
        // DB::connection('landlord')->beginTransaction();
        try {
            $filters = $request->only(['shipment_status', 'search', 'from', 'to', 'brand_name', 'medicine_name', 'generic_name', 'medicine_type']);
            $export = $request->input('export');

            $data = $this->inventoryService->all($filters, $export);

            if ($data instanceof \Symfony\Component\HttpFoundation\Response) {
                return $data;
            }

            if ($data->isEmpty()) {
                return JsonResponser::send(true, 'Shipment not found.', null, 200);
            }
            // DB::connection('landlord')->commit();
            return JsonResponser::send(false, 'Shipment list fetched successfully', $data);
        } catch (\InvalidArgumentException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }


    public function show($id)
    {
        try {
            $data = $this->inventoryService->find($id);
            if (!$data) {
                return JsonResponser::send(true, 'Shipment not found.', null, 200);
            }
            return JsonResponser::send(false, 'Shipment detail found', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function updateStatus($id)
    {
        try {
            $inventory = $this->inventoryService->find($id);

            if (!$inventory) {
                return JsonResponser::send(true, 'Shipment record not found.', [], 200);
            }

            $allowedStatuses = ['pending', 'incomplete', 'complete', 'received'];
            $newStatus = request()->input('shipment_status');

            if (!in_array($newStatus, $allowedStatuses)) {
                return JsonResponser::send(true, 'Invalid status provided.', [], 422);
            }

            $inventory->shipment_status = $newStatus;
            $inventory->save();

            return JsonResponser::send(false, 'Shipment status updated successfully.', $inventory, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function shipmentStat()
    {
        try {
            $data = $this->inventoryService->getShipmentStats();
            return JsonResponser::send(false, 'Shipment stats fetched successfully', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching shipment stats', [], 500, $e);
        }
    }
}
