<?php

namespace App\Repositories\PharmacySupplier;

use App\Models\PharmacySupply;

class PharmacySupplyRepository implements PharmacySupplyRepositoryInterface
{
    public function store(array $data)
    {
        return PharmacySupply::create($data);
    }

    public function getAll($search = null, $isExport = false)
    {
        $query = PharmacySupply::with('pharmacy');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                    ->orWhere('product_category', 'like', "%{$search}%")
                    ->orWhere('quantity_supplied', 'like', "%{$search}%")
                    ->orWhere('stock_level', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('batch_number', 'like', "%{$search}%")
                    ->orWhere('supplied_date', 'like', "%{$search}%")
                    ->orWhereHas('pharmacy', function ($qp) use ($search) {
                        $qp->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $query->orderByDesc('supplied_date');

        return $isExport ? $query->get() : $query->paginate(10);
    }


    public function findById(int $id)
    {
        return PharmacySupply::with('pharmacy')->find($id);
    }
}
