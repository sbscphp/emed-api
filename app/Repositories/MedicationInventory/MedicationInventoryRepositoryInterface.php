<?php

namespace App\Repositories\MedicationInventory;

interface MedicationInventoryRepositoryInterface
{
    public function create(array $data);
    public function getAllWithFilters(array $filters);
    public function find($id);
}
