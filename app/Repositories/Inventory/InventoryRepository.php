<?php

namespace App\Repositories\Inventory;

use App\Helpers\ExportHelper;
use App\Models\Inventory;
use Carbon\Carbon;

class InventoryRepository implements InventoryInterface
{
    /**
     * Retrieve a collection of Inventory from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Inventory::all();
    }

    public function getAllWithFilters(array $filters = [], ?string $export = null, $from, $to, $tenantId)
    {
        $query = Inventory::where('tenant_id', $tenantId)->with('medicineType');
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('batch_no', 'like', "%$search%")
                    ->orWhere('item_name', 'like', "%$search%")
                    ->orWhere('supplier', 'like', "%$search%")
                    ->orWhereHas('medicineType', function ($qu) use ($search) {
                        $qu->where("type_name", "%$search%");
                    });
            });
        }

        if (!empty($filters['type_name'])) {
            $query->whereHas('medicineType', function ($qu) use ($filters) {
                $qu->where("type_name", $filters['type_name']);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status'])
                ->whereDate('expiry_date', '>', now());
        }

        if (isset($filters['is_expired']) && filter_var($filters['is_expired'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereDate('expiry_date', '<=', now());
        }

        if (!empty($from) && !empty($to)) {
            $query->whereBetween('expiry_date', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay()
            ]);
        }

        $transformItem = function ($item) {
            return [
                'id'    => $item->id,
                'BatchNo' => $item->batch_no,
                'ItemName' => $item->item_name,
                'MedicineType' => $item->medicineType->type_name ?? '',
                'Quantity' => $item->quantity,
                'ReorderLevel' => $item->reorder_level,
                'Supplier' => $item->supplier,
                'ExpiryDate' => $item->expiry_date,
                'Note' => $item->note,
                'Status' => $item->status,
                'Created At' => $item->created_at->toDateTimeString(),
            ];
        };

        if ($export) {
            $items = $query->latest()->get()->map($transformItem);

            if ($export === 'csv') {
                return ExportHelper::streamCsv($items->toArray(), null, 'inventory.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($items->toArray(), 'inventory.pdf');
            }

            throw new \InvalidArgumentException('Invalid export format specified');
        }

        $paginated = $query->latest()->paginate(10);
        $paginated->getCollection()->transform($transformItem);

        return $paginated;
    }



    /**
     * Create new Inventory in the database.
     * 
     * @param array $data
     * @return \App\Models\Inventory
     */
    public function create(array $data)
    {
        return Inventory::create($data);
    }


    /**
     * Update an existing Inventory in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Inventory
     */
    public function update(array $data, $id)
    {
        $record = Inventory::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Inventory from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Inventory::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Inventory in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Inventory
     */
    public function find($id)
    {
        $inventory = Inventory::with('medicineType')->find($id);

        if (!$inventory) {
            return null;
        }

        return $inventory;
    }


    /**
     * Find an existing Inventory in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Inventory
     */
    public function findByAttribute($attr, $value)
    {
        return Inventory::where($attr, $value)->first();
    }
}
