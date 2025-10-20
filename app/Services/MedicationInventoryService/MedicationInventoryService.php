<?php

namespace App\Services\MedicationInventoryService;

use App\Enums\ListModuleEnums;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\UserMgtHelper;
use App\Models\MedicationInventory;
use App\Repositories\MedicationInventory\MedicationInventoryRepositoryInterface;
use Illuminate\Support\Facades\DB;

class MedicationInventoryService
{
    protected $inventoryRepository;

    public function __construct(MedicationInventoryRepositoryInterface $inventoryRepository)
    {
        $this->inventoryRepository = $inventoryRepository;
    }

    public function create(array $data)
    {
        return $this->inventoryRepository->create($data);
    }

    public function all(array $filters = [], ?string $export = null)
    {
        return $this->inventoryRepository->getAllWithFilters($filters, $export);
    }

    public function find($id)
    {
        return $this->inventoryRepository->find($id);
    }

    public function getShipmentStats($tenantId): array
    {

        return [
            'total_shipments' => MedicationInventory::where('tenant_id', $tenantId)->count(),

            'pending_shipments' => MedicationInventory::where('tenant_id', $tenantId)->where('shipment_status', 'pending')->count(),

            'received_shipments' => MedicationInventory::where('tenant_id', $tenantId)->where('shipment_status', 'received')->count(),

            'total_quantity_supplied' => MedicationInventory::where('tenant_id', $tenantId)->sum('received_qty'),

            'shipment_value' => 0,
            // 'shipment_value' => MedicationInventory::where('tenant_id', $tenantId)->join('medications', 'medications.id', '=', 'medication_inventory.medication_id')
            //     ->select(DB::raw('SUM(medication_inventory.received_qty * medications.cost_price) as total_value'))
            //     ->value('total_value'),
        ];
    }

    public function updateShipment($data, $shipment)
    {
        $currentUserInstance = UserMgtHelper::userInstance();
        $supportDoc = $shipment->support_doc;
        $supportFile = $shipment->support_file;
        if (!empty($data['support_doc'])) {
            if (filter_var($data['support_doc'], FILTER_VALIDATE_URL)) {
                // Already a URL (don’t upload again)
                $supportDoc = $data['support_doc'];
            } else {
                // Base64 string – upload
                $supportDoc = FileUploadHelper::singleStringFileUpload($data['support_doc'], 'shipment');
            }
        }
        if (!empty($data['support_file'])) {
            if (filter_var($data['support_file'], FILTER_VALIDATE_URL)) {
                // Already a URL (don’t upload again)
                $supportFile = $data['support_file'];
            } else {
                // Base64 string – upload
                $supportFile = FileUploadHelper::singleStringFileUpload($data['support_file'], 'shipment');
            }
        }
        // Update shipment fields
        $shipment->update([
            'vendor_id' => $data['vendor_id'],
            'date_of_shipment' => $data['date_of_shipment'],
            'expected_delivery_date' => $data['expected_delivery_date'],
            'courier_service' => $data['courier_service'],
            'tracking_number' => $data['tracking_number'],
            'order_placed_by' => $data['order_placed_by'],
            'delivery_location' => $data['delivery_location'],
            'dispatched_date' => $data['dispatched_date'],
            'current_location' => $data['current_location'],
            'delivery_note' => $data['delivery_note'],
            'support_doc' => $supportDoc,
            'support_file' => $supportFile,
            'brand_name' => $data['brand_name'],
            'active_ingredient' => $data['active_ingredient'],

        ]);


        $dataToLog = [
            'causer_id' => $currentUserInstance->id,
            'action_id' => $shipment->id,
            'action' => 'Update',
            'action_type' => "Models\MedicineInventory",
            'log_name' => "Medicine Inventory updated successfully",
            'description' => "{$currentUserInstance->firstname} {$currentUserInstance->lastname} updated a Medicine",
            'module_accessed' => ListModuleEnums::PHARMACY
        ];

        GeneralHelper::storeAuditLog($dataToLog);

        return $shipment->refresh();
    }
}
