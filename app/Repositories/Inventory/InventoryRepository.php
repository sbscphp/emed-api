<?php

namespace App\Repositories\Inventory;

use App\Helpers\ExportHelper;
use App\Models\Inventory;

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

    public function getAllWithFilters(array $filters = [], ?string $export = null)
    {
        $query = Inventory::with('medicineType');
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('batch_no', 'like', "%$search%")
                    ->orWhere('item_name', 'like', "%$search%")
                    ->orWhere('supplier', 'like', "%$search%");
            });
        }

        $transformItem = function ($item) {
            return [
                'Batch No' => $item->batch_no,
                'Item Name' => $item->item_name,
                'Medicine Type' => $item->medicineType->type_name ?? '',
                'Quantity' => $item->quantity,
                'Reorder Level' => $item->reorder_level,
                'Supplier' => $item->supplier,
                'Expiry Date' => $item->expiry_date,
                'Note' => $item->note,
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

        return [
            'id' => $inventory->id,
            'batch_no' => $inventory->batch_no,
            'item_name' => $inventory->item_name,
            'medicine_type_id' => $inventory->medicine_type_id,
            'medicine_type' => $inventory->medicineType->type_name ?? null,
            'quantity' => $inventory->quantity,
            'reorder_level' => $inventory->reorder_level,
            'supplier' => $inventory->supplier,
            'expiry_date' => $inventory->expiry_date,
            'note' => $inventory->note,
            'status' => $inventory->status,
            'created_at' => $inventory->created_at,
            'updated_at' => $inventory->updated_at,
        ];
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
