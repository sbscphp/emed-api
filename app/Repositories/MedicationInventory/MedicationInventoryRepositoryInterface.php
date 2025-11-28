<?php

namespace App\Repositories\MedicationInventory;

interface MedicationInventoryRepositoryInterface
{
    public function create(array $data);
    public function getAllWithFilters(array $filters, ?string $export = null, $tenantId = null);
    public function find($id);
}
