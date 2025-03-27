<?php

namespace App\Repositories\MedicationInventory;

use App\Models\MedicationInventory;

class MedicationInventoryRepository implements MedicationInventoryRepositoryInterface
{
    public function create(array $data)
    {
        $data['current_stock'] = $data['received_qty'];
        return MedicationInventory::create($data);
    }

    public function getAllWithFilters(array $filters = [])
    {
        $query = MedicationInventory::with(['medication', 'pharmacy']);

        if (!empty($filters['shipment_status'])) {
            $query->where('shipment_status', $filters['shipment_status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('batch_no', 'like', "%$search%")
                    ->orWhere('shipment_status', 'like', "%$search%")
                    ->orWhereHas('medication', function ($mq) use ($search) {
                        $mq->where('medicine_name', 'like', "%$search%")
                            ->orWhere('brand_name', 'like', "%$search%")
                            ->orWhere('generic_name', 'like', "%$search%");
                    })
                    ->orWhereHas('pharmacy', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%$search%");
                    });
            });
        }

        return $query->latest()->paginate(10);
    }


    public function find($id)
    {
        return MedicationInventory::with(['medication', 'pharmacy'])->find($id);
    }
}
