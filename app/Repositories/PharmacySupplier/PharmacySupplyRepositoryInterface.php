<?php

namespace App\Repositories\PharmacySupplier;

interface PharmacySupplyRepositoryInterface
{
    public function store(array $data);
    public function getAll($search = null,  $isExport = false);
    public function findById(int $id);
}
