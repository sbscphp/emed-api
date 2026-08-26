<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\ListModuleEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMedicineTypeRequest;
use App\Http\Requests\Admin\UpdateMedicineTypeRequest;
use App\Responser\JsonResponser;
use App\Services\MedicineType\MedicineTypeService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class MedicineTypeController extends Controller
{
    protected $userService;
    protected $medicineTypeService;

    public function __construct(UserService $userService, MedicineTypeService $medicineTypeService)
    {
        $this->userService = $userService;
        $this->medicineTypeService = $medicineTypeService;
    }
    public function index(Request $request)
    {
        try {
            $filters = $request->only(['search', 'type', 'export', 'from', 'to']);
            $types = $this->medicineTypeService->all($filters, $request);

            if (isset($filters['export'])) {
                $exportData = $types->map(function ($type) {
                    return [
                        'Type Name' => $type->type_name,
                        'Date Added' => $type->date_added,
                    ];
                });

                if ($filters['export'] === 'csv') {
                    return ExportHelper::streamCsv($exportData->toArray(), null, 'medicine_types.csv');
                }

                if ($filters['export'] === 'pdf') {
                    return ExportHelper::downloadPdf($exportData->toArray(), 'medicine_types.pdf');
                }

                return JsonResponser::send(true, 'Invalid export format.', [], 400);
            }

            return JsonResponser::send(false, 'Medicine types fetched successfully.', $types);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $e);
        }
    }

    public function store(StoreMedicineTypeRequest $request)
    {
        try {
            $currentUser = Auth::user();
            //$user = $this->userService->find($currentUser->id);
            $user = User::on('tenant')->where('email', $currentUser['email'])->first();
            $tenantId = $request->header('X-Tenant-ID');
            $validated = array_merge($request->validated(), [
                'tenant_id'        => $tenantId,
            ]);
            $created = $this->medicineTypeService->create($validated);
            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $created->id,
                'action' => 'Create',
                'action_type' => "Models\MedicineType",
                'log_name' => "Medicine Type created successfully",
                'description' => "{$currentUser->firstname} {$currentUser->lastname} created a new Medicine: {$created->name}",
                'module_accessed' => ListModuleEnums::PHARMACY
            ];

            GeneralHelper::storeAuditLog($dataToLog);
            return JsonResponser::send(false, 'Medicine type added successfully.', $created);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Failed to add medicine type.', [], 500, $e);
        }
    }

    public function show($id)
    {
        try {
            $type = $this->medicineTypeService->find($id);

            if (!$type) {
                return JsonResponser::send(true, 'Medicine type not found.', [], 200);
            }

            return JsonResponser::send(false, 'Medicine type retrieved successfully.', $type);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $e);
        }
    }

    public function update(UpdateMedicineTypeRequest $request, $id)
    {
        try {
            $updated = $this->medicineTypeService->update($request->validated(), $id);

            return JsonResponser::send(false, 'Medicine type updated successfully.', $updated);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JsonResponser::send(true, 'Medicine type not found.', [], 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $e);
        }
    }

    public function destroy($id)
    {
        try {
            $deleted = $this->medicineTypeService->delete($id);
            return JsonResponser::send(false, 'Medicine type deleted successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JsonResponser::send(true, 'Medicine type not found.', [], 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $e);
        }
    }
}
