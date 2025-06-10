<?php

namespace App\Services\Medication;

use App\Models\Medication;
use App\Models\MedicationInventory;
use App\Models\PharmacySupply;
use App\Repositories\Medication\MedicationRepositoryInterface;

class MedicationService
{
    protected $medicationRepo;

    public function __construct(MedicationRepositoryInterface $medicationRepo)
    {
        $this->medicationRepo = $medicationRepo;
    }

    public function all($data)
    {
        return $this->medicationRepo->all($data);
    }

    public function create(array $data)
    {
        return $this->medicationRepo->create($data);
    }

    public function find($id)
    {
        return $this->medicationRepo->find($id);
    }

    public function update($id, array $data)
    {
        return $this->medicationRepo->update($id, $data);
    }

    public function delete($id)
    {
        return $this->medicationRepo->delete($id);
    }

    public function getAllVendors()
    {
        return Medication::select('brand_name')
            ->distinct()
            ->orderBy('brand_name')
            ->pluck('brand_name');
    }

    public function getMedicineDashboardStats(): array
    {
        return [
            'total_medications' => Medication::count(),
            'total_supply_today' => PharmacySupply::whereDate('supplied_date', now())->count(),
            'near_expiry_medications' => MedicationInventory::whereBetween('expiry_date', [now(), now()->addDays(30)])
                ->distinct('medication_id')
                ->count('medication_id'),
            'low_stock_alert' => MedicationInventory::where('current_stock', '<', 10)->count(),
        ];
    }
}
