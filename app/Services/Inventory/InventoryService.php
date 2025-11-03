<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Repositories\Inventory\InventoryInterface;
use App\Responser\JsonResponser;
use Carbon\Carbon;

/**
 * Class InventoryService
 * 
 * This class provides services related to Inventory operations and acts as a 
 * layer between the Controller and the InventoryRepository.
 */
class InventoryService
{
    protected InventoryInterface $InventoryInterface;
    /**
     * Inventory constructor.
     * 
     * @param InventoryInterface $InventoryInterface
     */
    public function __construct(InventoryInterface $InventoryInterface)
    {
        $this->InventoryInterface = $InventoryInterface;
    }

    /**
     * Retrieve all Inventory.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all(array $filters = [], ?string $export = null, $from, $to, $tenantId)
    {
        return $this->InventoryInterface->getAllWithFilters($filters, $export, $from, $to, $tenantId);
    }


    /**
     * Create a new Inventory using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Inventory
     */
    public function create(array $data)
    {
        return $this->InventoryInterface->create($data);
    }


    /**
     * Update an existing Inventory with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Inventory
     */
    public function update(array $data, $id)
    {
        return $this->InventoryInterface->update($data, $id);
    }


    /**
     * Delete a Inventory by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->InventoryInterface->delete($id);
    }


    /**
     * Find a Inventory by their ID.
     * 
     * @param int $id
     * @return \App\Models\Inventory
     */
    public function find($id)
    {
        return $this->InventoryInterface->find($id);
    }


    /**
     * Find an existing Inventory  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Inventory
     */
    public function findByAttribute($attr, $value)
    {
        return $this->InventoryInterface->findByAttribute($attr, $value);
    }

    public function getInventoryStats($tenantId): array
    {
        return [
            'total_inventory_items' => Inventory::where('tenant_id', $tenantId)->count(),
            'stock_below_minimum' => Inventory::where('tenant_id', $tenantId)->whereColumn('quantity', '<', 'reorder_level')->count(),
            'expired_medicine' => Inventory::where('tenant_id', $tenantId)
                ->whereDate('expiry_date', '<=', now())
                ->count(),
            'pending_restock_requests' => Inventory::where('tenant_id', $tenantId)->where(function ($query) {
                $query->whereColumn('quantity', '<', 'reorder_level')
                    ->orWhereDate('expiry_date', '<', now());
            })->count(),
        ];
    }
}
