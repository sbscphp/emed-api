<?php

namespace App\Services\MedicationInventoryService;

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

    public function getShipmentStats(): array
    {
        return [
            'total_shipments' => MedicationInventory::count(),

            'pending_shipments' => MedicationInventory::where('shipment_status', 'pending')->count(),

            'received_shipments' => MedicationInventory::where('shipment_status', 'received')->count(),

            'total_quantity_supplied' => MedicationInventory::sum('received_qty'),

            'shipment_value' => MedicationInventory::join('medications', 'medications.id', '=', 'medication_inventory.medication_id')
                ->select(DB::raw('SUM(medication_inventory.received_qty * medications.cost_price) as total_value'))
                ->value('total_value'),
        ];
    }
}
