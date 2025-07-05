<?php

namespace App\Services\Laboratory;

use App\Repositories\Laboratory\LaboratoryInterface;

/**
 * Class LaboratoryService
 *
 * This class provides services related to Laboratory operations and acts as a
 * layer between the Controller and the LaboratoryRepository.
 */
class LaboratoryService
{
    protected LaboratoryInterface $LaboratoryInterface;
    /**
     * Laboratory constructor.
     *
     * @param LaboratoryInterface $LaboratoryInterface
     */
    public function __construct(LaboratoryInterface $LaboratoryInterface)
    {
        $this->LaboratoryInterface = $LaboratoryInterface;
    }

    /**
     * Retrieve all Laboratory.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->LaboratoryInterface->all();
    }

    /**
     * Create a new Laboratory using the data provided.
     *
     * @param array $data
     * @return \App\Models\Laboratory
     */
    public function create(array $data)
    {
        return $this->LaboratoryInterface->create($data);
    }


    /**
     * Update an existing Laboratory with the provided data.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Laboratory
     */
    public function update(array $data, $id)
    {
        return $this->LaboratoryInterface->update($data, $id);
    }


    /**
     * Delete a Laboratory by heir ID.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->LaboratoryInterface->delete($id);
    }


    /**
     * Find a Laboratory by their ID.
     *
     * @param int $id
     * @return \App\Models\Laboratory
     */
    public function find($id)
    {
        return $this->LaboratoryInterface->find($id);
    }


    /**
     * Find an existing Laboratory  by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Laboratory
     */
    public function findByAttribute($attr, $value)
    {
        return $this->LaboratoryInterface->findByAttribute($attr, $value);
    }

    public function getAllLabRecords($search, $status, $paginate, $paymentStatus, $perPage, $export, $from, $to)
    {
        return $this->LaboratoryInterface->getAllLabRecords($search, $status, $paginate, $paymentStatus, $perPage, $export, $from, $to);
    }

    public function getStats()
    {
        return $this->LaboratoryInterface->getStats();
    }
}
