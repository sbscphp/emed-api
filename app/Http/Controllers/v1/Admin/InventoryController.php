<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\Inventory\InventoryService;
use App\Helpers\GeneralHelper;
use App\Http\Requests\Admin\StoreInventoryRequest;
use App\Http\Requests\Admin\UpdateInventoryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    protected $service;

    public function __construct(InventoryService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        try {
            config(['database.default' => 'tenant']);
            $filters = $request->only(['search', 'type_name', 'status']);
            $export = $request->input('export');
            $from = $request->from;
            $to = $request->to;
            $data = $this->service->all($filters, $export, $from, $to);

            if ($data instanceof \Symfony\Component\HttpFoundation\Response) {
                return $data;
            }

            if ($data->isEmpty()) {
                return JsonResponser::send(true, 'Inventory not found.', null, 200);
            }

            return JsonResponser::send(false, 'Inventory list fetched successfully', $data);
        } catch (\InvalidArgumentException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }


    public function show($id)
    {
        config(['database.default' => 'tenant']);
        return response()->json($this->service->find($id));
    }

    public function store(StoreInventoryRequest $request)
    {
        config(['database.default' => 'tenant']);
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();
            $validated = array_merge($request->validated(), [
                'created_by' => $currentUser->id,
            ]);

            $inventory = $this->service->create($validated);

            GeneralHelper::storeAuditLog([
                'causer_id' => $currentUser->id,
                'action_id' => $inventory->id,
                'action' => 'Create',
                'action_type' => "Models\\Inventory",
                'log_name' => "Inventory item created",
                'description' => "{$currentUser->firstname} {$currentUser->lastname} added inventory: {$inventory->item_name} [Batch: {$inventory->batch_no}]",
                'module_accessed' => ListModuleEnums::Inventory
            ]);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Inventory created successfully', $inventory, 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function update(UpdateInventoryRequest $request, $id)
    {
        config(['database.default' => 'tenant']);
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();
            $validated = array_merge($request->validated(), [
                'updated_by' => $currentUser->id,
            ]);

            $inventory = $this->service->update($validated, $id);

            GeneralHelper::storeAuditLog([
                'causer_id' => $currentUser->id,
                'action_id' => $inventory->id,
                'action' => 'Update',
                'action_type' => "Models\\Inventory",
                'log_name' => "Inventory item updated",
                'description' => "{$currentUser->firstname} {$currentUser->lastname} updated inventory: {$inventory->item_name} [Batch: {$inventory->batch_no}]",
                'module_accessed' => ListModuleEnums::Inventory
            ]);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Inventory updated successfully', $inventory);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function destroy($id)
    {
        config(['database.default' => 'tenant']);
        $deleted = $this->service->delete($id);
        return JsonResponser::send(false, 'Inventory deleted successfully', $deleted);
    }

    public function getInventoryStats()
    {
        try {
            config(['database.default' => 'tenant']);
            $stats = $this->service->getInventoryStats();

            return JsonResponser::send(
                false,
                'Inventory statistics retrieved successfully',
                $stats,
                200
            );
        } catch (\Exception $e) {
            return JsonResponser::send(
                true,
                'Failed to retrieve inventory statistics',
                [],
                500,
                $e
            );
        }
    }
}
