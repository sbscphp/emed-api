<?php

namespace App\Services\PharmacySupplier;

use App\Repositories\PharmacySupplier\PharmacySupplyRepositoryInterface;

class PharmacySupplyService
{
    protected $supplyRepo;

    public function __construct(PharmacySupplyRepositoryInterface $supplyRepo)
    {
        $this->supplyRepo = $supplyRepo;
    }

    public function createSupply(array $data)
    {
        return $this->supplyRepo->store($data);
    }

    public function listSupplies($search,  $isExport, $from, $to)
    {
        return $this->supplyRepo->getAll($search,  $isExport, $from, $to);
    }

    public function getSupplyById(int $id)
    {
        return $this->supplyRepo->findById($id);
    }
}
