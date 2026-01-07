<?php

namespace App\Services\Medication;

use App\Models\Inventory;
use App\Models\Medication;
use App\Models\MedicationInventory;
use App\Models\PharmacyRequest;
use App\Models\PharmacySupply;
use App\Models\Treatment;
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

    public function getAllVendors($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        return Medication::select('brand_name')
            ->where('tenant_id', $tenantId)
            ->distinct()
            ->orderBy('brand_name')
            ->pluck('brand_name');
    }

    public function getMedicineDashboardStats($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        return [
            'total_medications' => Medication::where('tenant_id', $tenantId)->count(),
            // 'total_supply_today' => PharmacyRequest::where('tenant_id', $tenantId)->whereDate('supplied_date', now())->whereNotNull('supplied_date')->count(),
            'total_supply_today' => Treatment::where('tenant_id', $tenantId)->whereDate('dispensed_date', now())->whereNotNull('dispensed_date')->count(),
            // 'near_expiry_medications' => MedicationInventory::where('tenant_id', $tenantId)->whereBetween('expiry_date', [now(), now()->addDays(30)])
            //     ->distinct('medication_id')
            //     ->count('medication_id'),
            'low_stock_alert' => Inventory::where('tenant_id', $tenantId)->whereColumn('quantity', '<', 'reorder_level')->count(),
            'near_expiry_medications' => Inventory::where('tenant_id', $tenantId)->whereBetween('expiry_date', [now(), now()->addDays(30)])->count(),
        ];
    }
}
