<?php

namespace App\Services\Pharmacist;

use App\Repositories\Pharmacist\PharmacistInterface;

class PharmacistService
{
    protected $pharmacistRepository;

    public function __construct(PharmacistInterface $pharmacistInterface)
    {
        $this->pharmacistRepository = $pharmacistInterface;
    }

    public function create(array $data)
    {
        return $this->pharmacistRepository->create($data);
    }

    public function update($id, array $data)
    {
        return $this->pharmacistRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->pharmacistRepository->delete($id);
    }

    public function find($id)
    {
        return $this->pharmacistRepository->find($id);
    }

    public function getAll()
    {
        return $this->pharmacistRepository->getAll();
    }
}
