<?php

namespace App\Repositories\PharmacySupplier;

interface PharmacySupplyRepositoryInterface
{
    public function store(array $data);
    public function getAll($search = null,  $isExport = false, $from, $to);
    public function findById(int $id);
}
